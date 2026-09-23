<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

trait IssuesKeycloakTokens
{
    private string $keycloakPrivatePem = '';

    private const KEYCLOAK_KID = 'test-kid';

    /**
     * Fake-able response for Keycloak's JWKS endpoint; merge it into the test's Http::fake().
     *
     * @return array<string, \Closure>
     */
    protected function keycloakJwksFake(): array
    {
        if ($this->keycloakPrivatePem === '') {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $this->keycloakPrivatePem);
            $rsa = openssl_pkey_get_details($key)['rsa'];
            $this->jwks = ['keys' => [[
                'kid' => self::KEYCLOAK_KID,
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
            ]]];
        }

        return ['sso.hands-on-technology.org/*' => Http::response($this->jwks)];
    }

    /** @var array<string, mixed> */
    private array $jwks = [];

    /**
     * @param  list<string>  $realmRoles
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    protected function bearer(array $realmRoles = [], array $overrides = []): array
    {
        $this->keycloakJwksFake();
        $payload = array_merge([
            'iss' => 'https://sso.hands-on-technology.org/realms/master',
            'azp' => 'hero',
            'aud' => 'account',
            'sub' => 'user-1',
            'exp' => time() + 300,
            'email' => 'ada@example.org',
            'realm_access' => ['roles' => $realmRoles],
        ], $overrides);

        $token = JWT::encode($payload, $this->keycloakPrivatePem, 'RS256', self::KEYCLOAK_KID);

        return ['Authorization' => 'Bearer '.$token];
    }
}
