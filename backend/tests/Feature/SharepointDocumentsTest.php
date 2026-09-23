<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SharepointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IssuesKeycloakTokens;
use Tests\TestCase;

class SharepointDocumentsTest extends TestCase
{
    use IssuesKeycloakTokens;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'hero.sharepoint.tenant_id' => 'tenant',
            'hero.sharepoint.client_id' => 'client',
            'hero.sharepoint.client_secret' => 'secret',
        ]);
        Setting::put(SharepointService::SETTING_FOLDER_URL, 'https://example.sharepoint.com/:f:/s/hero/HeroFolder');
        Setting::put(SharepointService::SETTING_ROOT, ['id' => 'root', 'drive_id' => 'drive-1', 'name' => 'HERO-Dokumente']);

        Http::fake([
            ...$this->keycloakJwksFake(),
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token']),
            'graph.microsoft.com/v1.0/drives/drive-1/items/root/children*' => Http::response(['value' => [
                ['id' => 'file-1', 'name' => 'b.pdf', 'file' => ['mimeType' => 'application/pdf'], 'size' => 3],
                ['id' => 'sub', 'name' => 'Vorlagen', 'folder' => []],
            ]]),
            'graph.microsoft.com/v1.0/drives/drive-1/items/sub/children*' => Http::response(['value' => []]),
            'graph.microsoft.com/v1.0/drives/drive-1/items/file-1/content' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf']),
            'graph.microsoft.com/v1.0/drives/drive-1/items/file-1*' => Http::response([
                'id' => 'file-1', 'name' => 'b.pdf', 'file' => ['mimeType' => 'application/pdf'], 'parentReference' => ['id' => 'root'],
            ]),
            'graph.microsoft.com/v1.0/drives/drive-1/items/sub*' => Http::response([
                'id' => 'sub', 'name' => 'Vorlagen', 'webUrl' => 'https://example.sharepoint.com/Vorlagen', 'parentReference' => ['id' => 'root'],
            ]),
            'graph.microsoft.com/v1.0/drives/drive-1/items/elsewhere*' => Http::response([
                'id' => 'elsewhere', 'name' => 'x.pdf', 'file' => ['mimeType' => 'application/pdf'], 'parentReference' => ['id' => 'other-root'],
            ]),
            'graph.microsoft.com/v1.0/drives/drive-1/items/other-root*' => Http::response([
                'id' => 'other-root', 'parentReference' => [],
            ]),
            'graph.microsoft.com/*' => Http::response(['webUrl' => 'https://example.sharepoint.com/HERO']),
        ]);
    }

    public function test_documents_require_login(): void
    {
        $this->getJson('/api/sharepoint/status')->assertUnauthorized();
        $this->getJson('/api/sharepoint/documents')->assertUnauthorized();
        $this->getJson('/api/sharepoint/documents-file-stream?drive_id=drive-1&item_id=file-1')->assertUnauthorized();
    }

    public function test_documents_require_hero_user_client_role(): void
    {
        $this->getJson('/api/sharepoint/documents', $this->bearer())
            ->assertForbidden()
            ->assertJson(['error' => 'Forbidden - hero-user role required']);

        $this->getJson('/api/sharepoint/documents', $this->bearer(['hero-user']))->assertForbidden();

        $this->getJson('/api/sharepoint/documents', $this->bearer([], [
            'resource_access' => ['other-client' => ['roles' => ['hero-user']]],
        ]))->assertForbidden();

        $this->getJson('/api/sharepoint/documents-file-stream?drive_id=drive-1&item_id=file-1', $this->bearer())
            ->assertForbidden();
    }

    public function test_admins_see_documents_without_hero_user(): void
    {
        $this->getJson('/api/sharepoint/status', $this->bearer(['hero_admin']))->assertOk();
    }

    public function test_status(): void
    {
        $this->getJson('/api/sharepoint/status', $this->userBearer())
            ->assertOk()
            ->assertExactJson([
                'configured' => true,
                'folder_name' => 'HERO-Dokumente',
                'folder_url' => 'https://example.sharepoint.com/:f:/s/hero/HeroFolder',
            ]);
    }

    public function test_lists_root_with_folders_first(): void
    {
        $this->getJson('/api/sharepoint/documents', $this->userBearer())
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('breadcrumbs', [['id' => 'root', 'name' => 'HERO-Dokumente']])
            ->assertJsonPath('items.0.name', 'Vorlagen')
            ->assertJsonPath('items.0.type', 'folder')
            ->assertJsonPath('items.1.name', 'b.pdf')
            ->assertJsonPath('items.1.drive_id', 'drive-1');
    }

    public function test_lists_subfolder_with_breadcrumbs(): void
    {
        $this->getJson('/api/sharepoint/documents?item_id=sub', $this->userBearer())
            ->assertOk()
            ->assertJsonPath('breadcrumbs', [
                ['id' => 'root', 'name' => 'HERO-Dokumente'],
                ['id' => 'sub', 'name' => 'Vorlagen'],
            ])
            ->assertJsonPath('folder_web_url', 'https://example.sharepoint.com/Vorlagen');
    }

    public function test_streams_files_inside_the_folder(): void
    {
        $response = $this->get('/api/sharepoint/documents-file-stream?drive_id=drive-1&item_id=file-1', $this->userBearer());

        $response->assertOk();
        $this->assertSame('%PDF', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_refuses_files_outside_the_folder(): void
    {
        $this->getJson('/api/sharepoint/documents-file-stream?drive_id=drive-1&item_id=elsewhere', $this->userBearer())
            ->assertStatus(422)
            ->assertJson(['error' => 'Datei liegt nicht im konfigurierten SharePoint-Ordner.']);

        $this->getJson('/api/sharepoint/documents-file-stream?drive_id=other-drive&item_id=file-1', $this->userBearer())
            ->assertStatus(422);
    }

    public function test_not_configured_without_link(): void
    {
        Setting::put(SharepointService::SETTING_FOLDER_URL, null);

        $this->getJson('/api/sharepoint/documents', $this->userBearer())
            ->assertOk()
            ->assertExactJson(['configured' => false, 'items' => [], 'breadcrumbs' => []]);
    }
}
