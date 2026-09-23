<?php

namespace App\Support;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class KeycloakToken
{
    private const JWKS_CACHE_KEY = 'keycloak_jwks';

    /**
     * Claims of a valid HERO access token, or null when missing/invalid.
     *
     * @return array<string, mixed>|null
     */
    public static function claims(Request $request): ?array
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        try {
            $claims = self::decode($token);
        } catch (Throwable $e) {
            Log::info('Rejected Keycloak token', ['error' => $e->getMessage()]);

            return null;
        }

        if (($claims['iss'] ?? null) !== config('hero.keycloak.issuer')) {
            return null;
        }
        if (($claims['azp'] ?? null) !== config('hero.keycloak.client_id')) {
            return null;
        }

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    public static function roles(array $claims): array
    {
        return array_values(array_map('strval', array_merge(
            (array) data_get($claims, 'realm_access.roles', []),
            self::clientRoles($claims),
        )));
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    public static function clientRoles(array $claims): array
    {
        $clientId = (string) config('hero.keycloak.client_id');

        return array_values(array_map('strval', (array) data_get($claims, "resource_access.{$clientId}.roles", [])));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function isUser(array $claims): bool
    {
        return in_array(config('hero.keycloak.user_role'), self::clientRoles($claims), true)
            || self::isAdmin($claims);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function isAdmin(array $claims): bool
    {
        return in_array(config('hero.keycloak.admin_role'), self::roles($claims), true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $token): array
    {
        try {
            return (array) JWT::decode($token, JWK::parseKeySet(self::jwks()));
        } catch (\UnexpectedValueException $e) {
            // Keycloak rotated its keys: refetch once when the token's kid is unknown.
            if (! str_contains($e->getMessage(), '"kid"')) {
                throw $e;
            }
            Cache::forget(self::JWKS_CACHE_KEY);

            return (array) JWT::decode($token, JWK::parseKeySet(self::jwks()));
        }
    }

    /**
     * @return array{keys: array<int, array<string, mixed>>}
     */
    private static function jwks(): array
    {
        return Cache::remember(self::JWKS_CACHE_KEY, config('hero.keycloak.jwks_cache_seconds'), function () {
            $url = rtrim((string) config('hero.keycloak.issuer'), '/').'/protocol/openid-connect/certs';
            $keys = Http::timeout(10)->get($url)->throw()->json();
            // Keycloak also publishes encryption keys; only signing keys can verify tokens.
            $keys['keys'] = array_values(array_filter(
                $keys['keys'] ?? [],
                fn ($key) => ($key['use'] ?? 'sig') === 'sig',
            ));

            return $keys;
        });
    }
}
