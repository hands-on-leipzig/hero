<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IssuesKeycloakTokens;
use Tests\TestCase;

class VolunteerProxyTest extends TestCase
{
    use IssuesKeycloakTokens;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hero.flow.api_url' => 'https://flow.test/api']);
    }

    public function test_openings_come_from_flow_and_are_cached(): void
    {
        Http::fake(['flow.test/api/public/volunteer-openings' => Http::response(['data' => [['event_id' => 1]]])]);

        $this->getJson('/api/volunteer-openings')->assertOk()->assertExactJson(['data' => [['event_id' => 1]]]);
        $this->getJson('/api/volunteer-openings')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_openings_report_flow_outage(): void
    {
        Http::fake(['flow.test/*' => Http::response('down', 503)]);

        $this->getJson('/api/volunteer-openings')->assertStatus(502);
    }

    public function test_inquiry_forwards_known_fields_and_token(): void
    {
        Http::fake([
            ...$this->keycloakJwksFake(),
            'flow.test/api/public/volunteer-inquiries' => Http::response(['id' => 7], 201),
        ]);
        $headers = $this->bearer();

        $this->postJson('/api/volunteer-inquiries', [
            'event_id' => 1,
            'role' => 'Schiedsrichter',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.org',
            'is_admin' => true,
        ], $headers)->assertCreated()->assertExactJson(['id' => 7]);

        Http::assertSent(function (Request $request) use ($headers) {
            return $request->url() === 'https://flow.test/api/public/volunteer-inquiries'
                && $request->header('Authorization')[0] === $headers['Authorization']
                && $request['first_name'] === 'Ada'
                && ! isset($request['is_admin']);
        });
    }

    public function test_inquiry_passes_flow_validation_errors_through(): void
    {
        Http::fake(['flow.test/*' => Http::response([
            'message' => 'Die E-Mail ist ungültig.',
            'errors' => ['email' => ['Die E-Mail ist ungültig.']],
        ], 422)]);

        $this->postJson('/api/volunteer-inquiries', ['email' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Die E-Mail ist ungültig.');
    }

    public function test_inquiry_reports_flow_outage(): void
    {
        Http::fake(['flow.test/*' => Http::response('boom', 500)]);

        $this->postJson('/api/volunteer-inquiries', ['email' => 'ada@example.org'])->assertStatus(502);
    }
}
