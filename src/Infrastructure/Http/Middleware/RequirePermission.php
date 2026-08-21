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

        $reqRes = strtolower($this->resource);
        $reqAct = strtolower($this->action);
        $required = "{$reqRes}:{$reqAct}";
        
        $permissions = array_map('strtolower', $permissions);

        $hasPermission = in_array($required, $permissions, true)
            || in_array('*:*', $permissions, true)
            || in_array("{$reqRes}:*", $permissions, true);

        if (!$hasPermission) {
            return new Response(
                ['error' => "Forbidden: Missing permission '{$this->resource}:{$this->action}'."],
                403
            );
        }

        // Tenant boundary guard
        $userTenant = null;
        if (isset($request->attributes)) {
            $userTenant = $request->attributes->get('auth.tenantId');
        } elseif (method_exists($request, 'input')) {
            $userTenant = $request->input('_auth_tenant_id');
        }

        $requestedTenant = null;
        if (method_exists($request, 'input')) {
            $requestedTenant = $request->input('tenantId');
        }
        
        if ($requestedTenant && $userTenant && $requestedTenant !== $userTenant) {
            return new Response(['error' => 'Forbidden: Cross-tenant access is not allowed.'], 403);
        }

        return $next($request);
    }
}
