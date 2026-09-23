<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SharepointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IssuesKeycloakTokens;
use Tests\TestCase;

class AdminSharepointTest extends TestCase
{
    use IssuesKeycloakTokens;
    use RefreshDatabase;

    private const FOLDER = 'https://example.sharepoint.com/:f:/s/hero/HeroFolder';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'hero.sharepoint.tenant_id' => 'tenant',
            'hero.sharepoint.client_id' => 'client',
            'hero.sharepoint.client_secret' => 'secret',
        ]);
        Http::fake($this->keycloakJwksFake());
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/admin/sharepoint')->assertUnauthorized();
    }

    public function test_requires_hero_admin_role(): void
    {
        $this->getJson('/api/admin/sharepoint', $this->bearer(['flow_admin']))->assertForbidden();
    }

    public function test_rejects_tokens_of_other_clients(): void
    {
        $this->getJson('/api/admin/sharepoint', $this->bearer(['hero_admin'], ['azp' => 'flow']))
            ->assertUnauthorized();
    }

    public function test_rejects_tokens_of_other_issuers(): void
    {
        $this->getJson('/api/admin/sharepoint', $this->bearer(['hero_admin'], ['iss' => 'https://evil.example/realms/master']))
            ->assertUnauthorized();
    }

    public function test_hero_client_role_is_accepted(): void
    {
        $this->getJson('/api/admin/sharepoint', $this->bearer([], ['resource_access' => ['hero' => ['roles' => ['hero_admin']]]]))
            ->assertOk();
    }

    public function test_shows_config_without_secrets(): void
    {
        $this->getJson('/api/admin/sharepoint', $this->bearer(['hero_admin']))
            ->assertOk()
            ->assertExactJson([
                'folder_url' => null,
                'folder_name' => null,
                'has_credentials' => true,
                'configured' => false,
            ]);
    }

    public function test_reports_missing_credentials(): void
    {
        config(['hero.sharepoint.client_secret' => null]);
        Setting::put(SharepointService::SETTING_FOLDER_URL, self::FOLDER);

        $this->getJson('/api/admin/sharepoint', $this->bearer(['hero_admin']))
            ->assertOk()
            ->assertJson(['has_credentials' => false, 'configured' => false]);
    }

    public function test_update_stores_link_and_clears_resolved_root(): void
    {
        Setting::put(SharepointService::SETTING_ROOT, ['id' => 'old', 'drive_id' => 'old', 'name' => 'Alt']);

        $this->putJson('/api/admin/sharepoint', ['folder_url' => self::FOLDER], $this->bearer(['hero_admin']))
            ->assertOk()
            ->assertJsonPath('config.folder_url', self::FOLDER)
            ->assertJsonPath('config.configured', true);

        $this->assertSame(self::FOLDER, Setting::get(SharepointService::SETTING_FOLDER_URL));
        $this->assertNull(Setting::get(SharepointService::SETTING_ROOT));
    }

    public function test_update_rejects_non_url(): void
    {
        $this->putJson('/api/admin/sharepoint', ['folder_url' => 'kein link'], $this->bearer(['hero_admin']))
            ->assertUnprocessable();
    }

    public function test_clearing_the_link_turns_documents_off(): void
    {
        Setting::put(SharepointService::SETTING_FOLDER_URL, self::FOLDER);

        $this->putJson('/api/admin/sharepoint', ['folder_url' => null], $this->bearer(['hero_admin']))
            ->assertOk()
            ->assertJsonPath('config.configured', false);

        $this->getJson('/api/sharepoint/status', $this->userBearer())->assertJson(['configured' => false]);
    }

    public function test_connection_test_resolves_the_folder(): void
    {
        Setting::put(SharepointService::SETTING_FOLDER_URL, self::FOLDER);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token']),
            'graph.microsoft.com/v1.0/shares/*' => Http::response([
                'id' => 'root-item',
                'name' => 'HERO-Dokumente',
                'folder' => ['childCount' => 2],
                'parentReference' => ['driveId' => 'drive-1'],
            ]),
            'graph.microsoft.com/v1.0/drives/drive-1/items/root-item/children*' => Http::response(['value' => [
                ['id' => 'a', 'name' => 'Plan.pdf', 'file' => ['mimeType' => 'application/pdf'], 'size' => 10],
                ['id' => 'b', 'name' => 'Vorlagen', 'folder' => []],
            ]]),
            'graph.microsoft.com/*' => Http::response(['webUrl' => 'https://example.sharepoint.com/HERO']),
        ]);

        $this->postJson('/api/admin/sharepoint/test', [], $this->bearer(['hero_admin']))
            ->assertOk()
            ->assertExactJson(['success' => true, 'folder_name' => 'HERO-Dokumente', 'item_count' => 2]);

        $this->assertSame('HERO-Dokumente', Setting::get(SharepointService::SETTING_ROOT)['name']);
    }

    public function test_connection_test_reports_graph_errors(): void
    {
        Setting::put(SharepointService::SETTING_FOLDER_URL, self::FOLDER);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->postJson('/api/admin/sharepoint/test', [], $this->bearer(['hero_admin']))
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('error', fn ($error) => str_contains($error, 'Azure-Anmeldung fehlgeschlagen'));
    }
}
