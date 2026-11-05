<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharepointService
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public const SETTING_FOLDER_URL = 'sharepoint.folder_url';

    public const SETTING_FOLDER_URL_DE = 'sharepoint.folder_url_de';

    public const SETTING_FOLDER_URL_EN = 'sharepoint.folder_url_en';

    /** Resolved root of the legacy folder link: {id, drive_id, name}. Cleared when the link changes. */
    public const SETTING_ROOT = 'sharepoint.root';

    /** Resolved roots keyed by sha1 of the folder URL. */
    public const SETTING_ROOTS = 'sharepoint.roots';

    public function hasCredentials(): bool
    {
        return filled(config('hero.sharepoint.tenant_id'))
            && filled(config('hero.sharepoint.client_id'))
            && filled(config('hero.sharepoint.client_secret'));
    }

    public function normalizeLang(?string $lang): string
    {
        $lang = strtolower(trim((string) $lang));

        return str_starts_with($lang, 'en') ? 'en' : 'de';
    }

    public function folderUrl(?string $lang = null): ?string
    {
        return $this->folderUrlFor($lang);
    }

    public function folderUrlFor(?string $lang = null): ?string
    {
        $lang = $this->normalizeLang($lang);
        $legacy = $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL));
        $de = $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL_DE));
        $en = $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL_EN));

        if ($de === null && $en === null) {
            return $legacy;
        }

        $de ??= $legacy;
        $en ??= $legacy;
        $primary = $lang === 'en' ? $en : $de;
        $secondary = $lang === 'en' ? $de : $en;

        return $primary ?? $secondary;
    }

    public function isConfigured(): bool
    {
        return $this->hasCredentials() && filled($this->folderUrlFor());
    }

    public function getStatus(?string $lang = null): array
    {
        $lang = $this->normalizeLang($lang);
        $url = $this->folderUrlFor($lang);
        $root = $url ? $this->readCachedRoot($url) : null;

        return [
            'configured' => $this->isConfigured() && filled($url),
            'lang' => $lang,
            'folder_name' => $root['name'] ?? null,
            'folder_url' => $url,
        ];
    }

    public function getAdminConfig(): array
    {
        $deUrl = $this->folderUrlFor('de');
        $deRoot = $deUrl ? $this->readCachedRoot($deUrl) : null;

        return [
            'folder_url' => $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL)),
            'folder_url_de' => $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL_DE)),
            'folder_url_en' => $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL_EN)),
            'folder_name' => $deRoot['name'] ?? null,
            'has_credentials' => $this->hasCredentials(),
            'configured' => $this->isConfigured(),
        ];
    }

    public function updateFolderUrl(?string $folderUrl): void
    {
        $folderUrl = $this->cleanUrl($folderUrl);
        if ($folderUrl === $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL))) {
            return;
        }

        Setting::put(self::SETTING_FOLDER_URL, $folderUrl);
        Setting::put(self::SETTING_ROOT, null);
        Setting::put(self::SETTING_ROOTS, null);
    }

    public function updateLanguageFolderUrls(?string $de, ?string $en): void
    {
        $de = $this->cleanUrl($de);
        $en = $this->cleanUrl($en);
        $changed = $de !== $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL_DE))
            || $en !== $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL_EN));

        Setting::put(self::SETTING_FOLDER_URL_DE, $de);
        Setting::put(self::SETTING_FOLDER_URL_EN, $en);
        Setting::put(self::SETTING_FOLDER_URL, $de ?? $en);

        if ($changed) {
            Setting::put(self::SETTING_ROOT, null);
            Setting::put(self::SETTING_ROOTS, null);
        }
    }

    /**
     * @return array{items: array, breadcrumbs: array, current_item_id: string, drive_id: string, folder_name: ?string, folder_web_url: ?string}
     */
    public function listFolder(?string $itemId = null, ?string $lang = null): array
    {
        $folderUrl = $this->folderUrlFor($lang);
        if (! $this->hasCredentials() || $folderUrl === null) {
            throw new \RuntimeException('SharePoint ist nicht konfiguriert.');
        }

        $token = $this->getAccessToken();
        $root = $this->resolveRootFolder($token, $folderUrl);
        $driveId = $root['drive_id'];

        $isRoot = $itemId === null || $itemId === $root['id'];

        if ($itemId === null) {
            $itemId = $root['id'];
            $breadcrumbs = [['id' => $itemId, 'name' => $root['name']]];
        } else {
            $this->assertItemAllowed($driveId, $itemId, $token);
            $breadcrumbs = $this->buildBreadcrumbs($root, $driveId, $itemId, $token);
        }

        $children = $this->fetchChildren($driveId, $itemId, $token);

        // At the root, keep the admin-configured link as-is (it may carry anonymous/guest
        // access that Graph's own webUrl for the item does not). Only resolve a webUrl via
        // Graph when navigating into a subfolder, where no admin link exists.
        $folderWebUrl = $isRoot
            ? ($folderUrl ?: $this->getItemWebUrl($driveId, $itemId, $token))
            : ($this->getItemWebUrl($driveId, $itemId, $token) ?: $folderUrl);

        return [
            'items' => $children,
            'breadcrumbs' => $breadcrumbs,
            'current_item_id' => $itemId,
            'drive_id' => $driveId,
            'folder_name' => $breadcrumbs[count($breadcrumbs) - 1]['name'] ?? null,
            'folder_web_url' => $folderWebUrl ?: null,
        ];
    }

    /**
     * @return array{body: string, content_type: string, filename: string}
     */
    public function streamFileContent(string $driveId, string $itemId): array
    {
        $driveId = trim($driveId);
        $itemId = trim($itemId);
        if ($driveId === '' || $itemId === '') {
            throw new \RuntimeException('drive_id und item_id sind erforderlich.');
        }
        if (! $this->isConfigured()) {
            throw new \RuntimeException('SharePoint ist nicht konfiguriert.');
        }

        $token = $this->getAccessToken();
        $this->assertItemAllowed($driveId, $itemId, $token);

        $binary = $this->fetchDriveItemContentBinary($driveId, $itemId, $token);
        if ($binary === null) {
            throw new \RuntimeException('Datei konnte nicht aus SharePoint geladen werden.');
        }

        return $binary;
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('SharePoint ist nicht konfiguriert.');
        }

        $languages = [];
        $byUrl = [];
        foreach (['de', 'en'] as $lang) {
            $url = $this->folderUrlFor($lang);
            if ($url === null) {
                $languages[$lang] = ['success' => false, 'error' => 'Kein Link hinterlegt.', 'folder_url' => null];

                continue;
            }
            if (! isset($byUrl[$url])) {
                try {
                    $result = $this->listFolder(null, $lang);
                    $byUrl[$url] = [
                        'success' => true,
                        'folder_name' => $result['folder_name'],
                        'item_count' => count($result['items']),
                        'folder_url' => $url,
                    ];
                } catch (\RuntimeException $e) {
                    $byUrl[$url] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'folder_url' => $url,
                    ];
                }
            }
            $languages[$lang] = $byUrl[$url];
        }

        $primary = ($languages['de']['success'] ?? false) ? $languages['de'] : ($languages['en'] ?? ['success' => false]);
        if (! ($primary['success'] ?? false)) {
            throw new \RuntimeException($primary['error'] ?? 'Verbindungstest fehlgeschlagen.');
        }

        return [
            'success' => ($languages['de']['success'] ?? false) && ($languages['en']['success'] ?? false),
            'folder_name' => $primary['folder_name'] ?? null,
            'item_count' => $primary['item_count'] ?? 0,
            'languages' => $languages,
        ];
    }

    private function getAccessToken(): string
    {
        $tenantId = (string) config('hero.sharepoint.tenant_id');
        $clientId = (string) config('hero.sharepoint.client_id');

        return Cache::remember('sharepoint_access_token_'.$clientId, 3500, function () use ($tenantId, $clientId) {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $clientId,
                    'client_secret' => (string) config('hero.sharepoint.client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

            if (! $response->successful()) {
                Log::error('SharePoint token request failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                throw new \RuntimeException(
                    'Azure-Anmeldung fehlgeschlagen. Tenant-ID, Client-ID und Client-Secret in der HERO-Konfiguration prüfen.'
                );
            }

            $token = $response->json('access_token');
            if (! $token) {
                throw new \RuntimeException('Kein Zugriffstoken von Azure erhalten.');
            }

            return $token;
        });
    }

    /**
     * @return array{id: string, drive_id: string, name: string}
     */
    private function resolveRootFolder(string $token, ?string $folderUrl = null): array
    {
        $folderUrl = $this->cleanUrl($folderUrl) ?? $this->folderUrlFor();
        if ($folderUrl === null) {
            throw new \RuntimeException('SharePoint ist nicht konfiguriert.');
        }

        $cached = $this->readCachedRoot($folderUrl);
        if ($cached !== null) {
            return $cached;
        }

        $shareId = $this->encodeShareUrl($folderUrl);
        $response = Http::withToken($token)->get(self::GRAPH_BASE."/shares/{$shareId}/driveItem");

        if (! $response->successful()) {
            Log::error('SharePoint folder resolve failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \RuntimeException(
                'SharePoint-Ordner konnte nicht geöffnet werden. Link und App-Berechtigungen (Sites.Read.All) prüfen.'
            );
        }

        $item = $response->json();
        if (($item['folder'] ?? null) === null && ($item['file'] ?? null) !== null) {
            throw new \RuntimeException('Der Link zeigt auf eine Datei, nicht auf einen Ordner.');
        }

        $driveId = $item['parentReference']['driveId'] ?? null;
        $itemId = $item['id'] ?? null;
        if (! $driveId || ! $itemId) {
            throw new \RuntimeException('SharePoint-Ordner konnte nicht aufgelöst werden.');
        }

        $root = ['id' => $itemId, 'drive_id' => $driveId, 'name' => $item['name'] ?? 'Ordner'];
        $this->writeCachedRoot($folderUrl, $root);

        return $root;
    }

    private function cleanUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }
        $url = trim($url);

        return $url !== '' ? $url : null;
    }

    /**
     * @return list<string>
     */
    private function configuredFolderUrls(): array
    {
        $urls = [];
        foreach (['de', 'en'] as $lang) {
            $url = $this->folderUrlFor($lang);
            if ($url !== null) {
                $urls[$url] = $url;
            }
        }

        return array_values($urls);
    }

    /**
     * @return array{id: string, drive_id: string, name: string}|null
     */
    private function readCachedRoot(string $url): ?array
    {
        $roots = Setting::get(self::SETTING_ROOTS);
        if (is_array($roots)) {
            $hit = $roots[sha1($url)] ?? null;
            if (is_array($hit) && ! empty($hit['id']) && ! empty($hit['drive_id'])) {
                return $hit;
            }
        }

        if ($url === $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL))) {
            $cached = Setting::get(self::SETTING_ROOT);
            if (is_array($cached) && ! empty($cached['id']) && ! empty($cached['drive_id'])) {
                return $cached;
            }
        }

        return null;
    }

    /**
     * @param  array{id: string, drive_id: string, name: string}  $root
     */
    private function writeCachedRoot(string $url, array $root): void
    {
        $roots = Setting::get(self::SETTING_ROOTS);
        if (! is_array($roots)) {
            $roots = [];
        }
        $roots[sha1($url)] = $root;
        Setting::put(self::SETTING_ROOTS, $roots);
        if ($url === $this->cleanUrl(Setting::get(self::SETTING_FOLDER_URL))) {
            Setting::put(self::SETTING_ROOT, $root);
        }
    }

    private function encodeShareUrl(string $url): string
    {
        return 'u!'.strtr(rtrim(base64_encode($url), '='), '+/', '-_');
    }

    private function getItemWebUrl(string $driveId, string $itemId, string $token): ?string
    {
        $response = Http::withToken($token)
            ->get(self::GRAPH_BASE.'/drives/'.rawurlencode($driveId).'/items/'.rawurlencode($itemId), [
                '$select' => 'webUrl',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $url = trim((string) ($response->json('webUrl') ?? ''));

        return $url !== '' ? $url : null;
    }

    private function fetchChildren(string $driveId, string $itemId, string $token): array
    {
        $response = Http::withToken($token)
            ->get(self::GRAPH_BASE.'/drives/'.rawurlencode($driveId).'/items/'.rawurlencode($itemId).'/children', [
                '$orderby' => 'name',
                '$select' => 'id,name,size,lastModifiedDateTime,webUrl,folder,file',
            ]);

        if (! $response->successful()) {
            Log::error('SharePoint list children failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \RuntimeException('Ordnerinhalt konnte nicht geladen werden.');
        }

        $items = [];
        foreach ($response->json('value', []) as $entry) {
            $isFolder = isset($entry['folder']);
            $items[] = [
                'id' => $entry['id'],
                'drive_id' => $driveId,
                'name' => $entry['name'],
                'type' => $isFolder ? 'folder' : 'file',
                'size' => $isFolder ? null : ($entry['size'] ?? null),
                'modified' => $entry['lastModifiedDateTime'] ?? null,
                'web_url' => $entry['webUrl'] ?? null,
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * @param  array{id: string, drive_id: string, name: string}  $root
     * @return array<int, array{id: string, name: string}>
     */
    private function buildBreadcrumbs(array $root, string $driveId, string $itemId, string $token): array
    {
        $crumbs = [];
        $currentId = $itemId;

        for ($depth = 0; $currentId && $depth < 20; $depth++) {
            if ($currentId === $root['id']) {
                break;
            }

            $response = Http::withToken($token)
                ->get(self::GRAPH_BASE.'/drives/'.rawurlencode($driveId).'/items/'.rawurlencode($currentId), [
                    '$select' => 'id,name,parentReference',
                ]);
            if (! $response->successful()) {
                break;
            }

            $item = $response->json();
            array_unshift($crumbs, ['id' => $item['id'], 'name' => $item['name']]);
            $currentId = $item['parentReference']['id'] ?? null;
        }

        array_unshift($crumbs, ['id' => $root['id'], 'name' => $root['name']]);

        return $crumbs;
    }

    private function assertItemAllowed(string $driveId, string $itemId, string $token): void
    {
        if (! $this->isItemUnderRoot($driveId, $itemId, $token)) {
            throw new \RuntimeException('Datei liegt nicht im konfigurierten SharePoint-Ordner.');
        }
    }

    private function isItemUnderRoot(string $driveId, string $itemId, string $token): bool
    {
        foreach ($this->configuredFolderUrls() as $folderUrl) {
            try {
                $root = $this->resolveRootFolder($token, $folderUrl);
            } catch (\RuntimeException) {
                continue;
            }
            if ($this->itemIsUnderRoot($driveId, $itemId, $token, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{id: string, drive_id: string, name: string}  $root
     */
    private function itemIsUnderRoot(string $driveId, string $itemId, string $token, array $root): bool
    {
        if ($driveId !== $root['drive_id']) {
            return false;
        }

        $current = $itemId;
        for ($depth = 0; $depth < 40; $depth++) {
            if ($current === $root['id']) {
                return true;
            }

            $response = Http::withToken($token)
                ->get(self::GRAPH_BASE.'/drives/'.rawurlencode($driveId).'/items/'.rawurlencode($current), [
                    '$select' => 'id,parentReference',
                ]);
            if (! $response->successful()) {
                return false;
            }

            $parentId = $response->json('parentReference.id');
            if (! $parentId || $parentId === $current) {
                return false;
            }
            $current = $parentId;
        }

        return false;
    }

    /**
     * @return array{body: string, content_type: string, filename: string}|null
     */
    private function fetchDriveItemContentBinary(string $driveId, string $itemId, string $token): ?array
    {
        $itemUrl = self::GRAPH_BASE.'/drives/'.rawurlencode($driveId).'/items/'.rawurlencode($itemId);

        $meta = Http::withToken($token)->get($itemUrl, ['$select' => 'name,file']);
        if (! $meta->successful() || empty($meta->json('file'))) {
            return null;
        }

        $content = Http::withToken($token)
            ->withOptions(['allow_redirects' => true])
            ->get($itemUrl.'/content');

        if (! $content->successful() || $content->body() === '') {
            Log::error('SharePoint file stream failed', [
                'status' => $content->status(),
                'drive_id' => $driveId,
                'item_id' => $itemId,
            ]);

            return null;
        }

        return [
            'body' => $content->body(),
            'content_type' => $content->header('Content-Type') ?: 'application/octet-stream',
            'filename' => (string) ($meta->json('name') ?: 'download'),
        ];
    }
}
