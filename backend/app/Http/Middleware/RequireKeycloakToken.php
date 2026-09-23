<?php

namespace App\Http\Middleware;

use App\Support\KeycloakToken;
use Closure;
use Illuminate\Http\Request;

class RequireKeycloakToken
{
    public function handle(Request $request, Closure $next, ?string $requirement = null)
    {
        $claims = KeycloakToken::claims($request);
        if ($claims === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($requirement === 'admin' && ! KeycloakToken::isAdmin($claims)) {
            return response()->json(['error' => 'Forbidden - hero_admin role required'], 403);
        }
        if ($requirement === 'user' && ! KeycloakToken::isUser($claims)) {
            return response()->json(['error' => 'Forbidden - hero-user role required'], 403);
        }

        $request->attributes->set('jwt', $claims);

        return $next($request);
    }
}
