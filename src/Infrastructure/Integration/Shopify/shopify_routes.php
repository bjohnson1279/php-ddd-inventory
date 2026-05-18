<?php

/**
 * API Routes - Shopify Integration
 *
 * Register these in your HTTP kernel (app/Http/routes.php or routes/api.php).
 *
 * IMPORTANT: The /webhooks/shopify/* endpoints MUST be excluded from CSRF
 * middleware since Shopify is an external system that cannot supply a CSRF
 * token. Add them to the $except array in VerifyCsrfToken.php if using Laravel.
 *
 * Example Laravel registration:
 *   Route::prefix('webhooks/shopify')->group(function () {
 *       Route::post('orders/paid',     [ShopifyWebhookController::class, 'handleOrderPaid']);
 *       Route::post('refunds/create',  [ShopifyWebhookController::class, 'handleRefundCreated']);
 *   });
 */

use InventoryApp\Infrastructure\Integration\Shopify\ShopifyWebhookController;

return [
    [
        'method'     => 'POST',
        'uri'        => '/webhooks/shopify/orders/paid',
        'controller' => [ShopifyWebhookController::class, 'handleOrderPaid'],
        'middleware' => ['skip-csrf'],
    ],
    [
        'method'     => 'POST',
        'uri'        => '/webhooks/shopify/refunds/create',
        'controller' => [ShopifyWebhookController::class, 'handleRefundCreated'],
        'middleware' => ['skip-csrf'],
    ],
];
