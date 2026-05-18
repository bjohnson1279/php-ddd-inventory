<?php

namespace InventoryApp\Infrastructure\Http\Middleware;

use Closure;
use InventoryApp\Infrastructure\Identity\ApiTokenService;

/**
 * Validates the Bearer token on every protected API request.
 *
 * On success, sets two request attributes that downstream controllers
 * and use cases can read:
 *   - `auth.user_id`   : string
 *   - `auth.tenant_id` : string
 *
 * Usage (generic PSR-7 style, adapt as needed for your HTTP stack):
 *
 *   // In your routes or kernel, attach this before any protected route:
 *   Route::middleware([AuthMiddleware::class])->group(function () { ... });
 */
class AuthMiddleware
{
    private ApiTokenService $tokenService;

    public function __construct(ApiTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function handle($request, Closure $next)
    {
        $authHeader = $request->header('Authorization', '');
        $token      = $this->extractBearerToken($authHeader);

        if ($token === null) {
            return response()->json(['error' => 'Missing or malformed Authorization header'], 401);
        }

        $tokenData = $this->tokenService->validate($token);

        if ($tokenData === null) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Expose the identity on the request for use by controllers
        $request->attributes?->set('auth.user_id',   $tokenData->user_id);
        $request->attributes?->set('auth.tenant_id',  $tokenData->tenant_id);

        // Laravel-style: merge into request for easy access via $request->user_id etc.
        $request->merge([
            '_auth_user_id'   => $tokenData->user_id,
            '_auth_tenant_id' => $tokenData->tenant_id,
        ]);

        return $next($request);
    }

    private function extractBearerToken(string $header): ?string
    {
        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
            return $token !== '' ? $token : null;
        }
        return null;
    }
}
