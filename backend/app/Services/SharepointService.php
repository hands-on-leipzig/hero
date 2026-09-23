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

    /** Resolved root of the folder link: {id, drive_id, name}. Cleared when the link changes. */
    public const SETTING_ROOT = 'sharepoint.root';

    public function hasCredentials(): bool
    {
        return filled(config('hero.sharepoint.tenant_id'))
            && filled(config('hero.sharepoint.client_id'))
            && filled(config('hero.sharepoint.client_secret'));
    }

    public function folderUrl(): ?string
    {
        return Setting::get(self::SETTING_FOLDER_URL);
    }

    public function isConfigured(): bool
    {
        return $this->hasCredentials() && filled($this->folderUrl());
    }

    public function getStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'folder_name' => Setting::get(self::SETTING_ROOT)['name'] ?? null,
            'folder_url' => $this->folderUrl(),
        ];
    }

    public function getAdminConfig(): array
    {
        return [
            'folder_url' => $this->folderUrl(),
            'folder_name' => Setting::get(self::SETTING_ROOT)['name'] ?? null,
            'has_credentials' => $this->hasCredentials(),
            'configured' => $this->isConfigured(),
        ];
    }

    public function updateFolderUrl(?string $folderUrl): void
    {
        $folderUrl = filled($folderUrl) ? trim($folderUrl) : null;
        if ($folderUrl === $this->folderUrl()) {
            return;
        }

        Setting::put(self::SETTING_FOLDER_URL, $folderUrl);
        Setting::put(self::SETTING_ROOT, null);
    }

    /**
     * @return array{items: array, breadcrumbs: array, current_item_id: string, drive_id: string, folder_name: ?string, folder_web_url: ?string}
     */
    public function listFolder(?string $itemId = null): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('SharePoint ist nicht konfiguriert.');
        }

        $token = $this->getAccessToken();
        $root = $this->resolveRootFolder($token);
        $driveId = $root['drive_id'];

        if ($itemId === null) {
            $itemId = $root['id'];
            $breadcrumbs = [['id' => $itemId, 'name' => $root['name']]];
        } else {
            $this->assertItemAllowed($driveId, $itemId, $token);
            $breadcrumbs = $this->buildBreadcrumbs($root, $driveId, $itemId, $token);
        }

        $children = $this->fetchChildren($driveId, $itemId, $token);
        $folderWebUrl = $this->getItemWebUrl($driveId, $itemId, $token) ?: trim((string) $this->folderUrl());

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
        $result = $this->listFolder();

        return [
            'success' => true,
            'folder_name' => $result['folder_name'],
            'item_count' => count($result['items']),
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
    private function resolveRootFolder(string $token): array
    {
        $cached = Setting::get(self::SETTING_ROOT);
        if (! empty($cached['id']) && ! empty($cached['drive_id'])) {
            return $cached;
        }

        $shareId = $this->encodeShareUrl((string) $this->folderUrl());
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
        Setting::put(self::SETTING_ROOT, $root);

        return $root;
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
        $root = $this->resolveRootFolder($token);
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
