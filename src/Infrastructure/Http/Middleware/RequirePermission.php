<?php

namespace InventoryApp\Infrastructure\Http\Middleware;

use Closure;
use InventoryApp\Infrastructure\Http\Response;

/**
 * Permission-based authorization middleware.
 *
 * Checks whether the authenticated user holds a specific resource:action permission.
 * Permissions are decoded from the JWT token by AuthMiddleware and stored on the request.
 *
 * Usage:
 *   new RequirePermission('inventory', 'dispatch')
 */
class RequirePermission
{
    private string $resource;
    private string $action;

    public function __construct(string $resource, string $action)
    {
        $this->resource = $resource;
        $this->action = $action;
    }

    public function handle($request, Closure $next)
    {
        $permissions = [];

        // Try to read permissions from request attributes (PSR-7 style)
        if (isset($request->attributes)) {
            $permissions = $request->attributes->get('auth.permissions', []);
        }

        // Fallback: try Laravel-style merged attributes
        if (empty($permissions) && method_exists($request, 'input')) {
            $permissions = $request->input('_auth_permissions', []);
        }

        $required = "{$this->resource}:{$this->action}";

        if (!in_array($required, $permissions, true)) {
            return new Response(
                ['error' => "Forbidden: Missing permission '{$required}'."],
                403
            );
        }

        return $next($request);
    }
}
