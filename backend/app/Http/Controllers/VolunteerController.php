<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Volunteer openings and inquiries live in FLOW (the partners answer them there).
 * HERO reads and submits them through FLOW's public API, server-side.
 */
class VolunteerController extends Controller
{
    private const OPENINGS_CACHE_KEY = 'flow_volunteer_openings';

    public function openings(): JsonResponse
    {
        try {
            $body = Cache::remember(self::OPENINGS_CACHE_KEY, config('hero.flow.openings_cache_seconds'), function () {
                return $this->flow()->get('/public/volunteer-openings')->throw()->json();
            });
        } catch (\Throwable $e) {
            Log::warning('FLOW volunteer openings unavailable', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Offene Rollen konnten nicht geladen werden.'], 502);
        }

        return response()->json(['data' => is_array($body['data'] ?? null) ? $body['data'] : []]);
    }

    /** FLOW validates and answers (incl. its 422 messages); HERO only forwards the known fields. */
    public function inquire(Request $request): JsonResponse
    {
        $payload = $request->only(['event_id', 'role', 'first_name', 'last_name', 'email', 'mobile', 'message']);

        $flow = $this->flow();
        // FLOW links the inquiry to the signed-in person when it gets their token.
        if ($token = $request->bearerToken()) {
            $flow = $flow->withToken($token);
        }

        try {
            $response = $flow->post('/public/volunteer-inquiries', $payload);
        } catch (ConnectionException $e) {
            Log::warning('FLOW volunteer inquiry unavailable', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Anfrage konnte nicht gesendet werden.'], 502);
        }

        if ($response->serverError()) {
            Log::error('FLOW volunteer inquiry failed', ['status' => $response->status()]);

            return response()->json(['error' => 'Anfrage konnte nicht gesendet werden.'], 502);
        }

        return response()->json($response->json() ?? [], $response->status());
    }

    private function flow(): PendingRequest
    {
        return Http::baseUrl(config('hero.flow.api_url'))
            ->acceptJson()
            ->timeout(config('hero.flow.timeout'));
    }
}
