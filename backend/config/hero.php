<?php

return [

    'keycloak' => [
        'issuer' => env('KEYCLOAK_ISSUER', 'https://sso.hands-on-technology.org/realms/master'),
        // Only tokens issued to the HERO SPA are accepted (`azp`).
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'hero'),
        'admin_role' => 'hero_admin',
        'jwks_cache_seconds' => 3600,
    ],

    'flow' => [
        // Public FLOW API (volunteer openings + inquiries), called server-side.
        'api_url' => rtrim((string) env('FLOW_API_URL', 'https://flow.hands-on-technology.org/api'), '/'),
        'timeout' => 15,
        'openings_cache_seconds' => 60,
    ],

    'sharepoint' => [
        // HERO's own Entra app (application permission Sites.Read.All). The folder link is set in the admin UI.
        'tenant_id' => env('SHAREPOINT_TENANT_ID'),
        'client_id' => env('SHAREPOINT_CLIENT_ID'),
        'client_secret' => env('SHAREPOINT_CLIENT_SECRET'),
    ],

];
