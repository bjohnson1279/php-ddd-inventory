<?php

namespace InventoryApp\Infrastructure\Http\Middleware;

use Closure;

/**
 * Bypasses CSRF token verification for Shopify webhook endpoints.
 *
 * Shopify signs its webhook payloads with HMAC-SHA256 (verified by
 * ShopifyWebhookVerifier) — that is the security guarantee, not a CSRF token.
 *
 * Usage in a Laravel app — add to app/Http/Middleware/VerifyCsrfToken.php:
 *
 *   protected $except = [
 *       'webhooks/shopify/*',
 *   ];
 *
 * Or register this middleware directly on the webhook route group:
 *
 *   Route::prefix('webhooks/shopify')
 *       ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
 *       ->group(function () { ... });
 *
 * For non-Laravel frameworks, register this as a before-middleware on the
 * /webhooks/shopify/* route prefix. It sets a request attribute that your
 * CSRF middleware can check to skip verification.
 */
class SkipCsrfForShopifyWebhooks
{
    public function handle($request, Closure $next)
    {
        // Mark the request so any downstream CSRF middleware can skip it.
        // In Laravel this is handled declaratively via $except — this class
        // is provided as a reference for non-Laravel HTTP stacks.
        $request->attributes?->set('csrf_exempt', true);

        return $next($request);
    }
}
