<?php

namespace InventoryApp\Infrastructure\Integration\Shopify;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * HTTP entry point for all Shopify webhooks.
 *
 * Register these routes in your HTTP kernel (e.g., routes/api.php):
 *
 *   POST /webhooks/shopify/orders/paid
 *   POST /webhooks/shopify/refunds/create
 *
 * IMPORTANT: Shopify sends the raw body with the HMAC header
 * `X-Shopify-Hmac-SHA256`. The request body must NOT be decoded by
 * any middleware before it reaches verify() — use the raw body.
 */
class ShopifyWebhookController
{
    private ShopifyWebhookVerifier $verifier;
    private ShopifyOrderMapper $mapper;

    public function __construct(ShopifyWebhookVerifier $verifier, ShopifyOrderMapper $mapper)
    {
        $this->verifier = $verifier;
        $this->mapper   = $mapper;
    }

    public function handleOrderPaid(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->mapper->handleOrderPaid($payload);
            return response()->json(['message' => 'Order processed'], 200);
        } catch (Exception $e) {
            // Log the error; always return 200 to Shopify to prevent retries
            // when the error is a business logic issue rather than infra.
            report($e);
            return response()->json(['message' => 'Accepted'], 200);
        }
    }

    public function handleRefundCreated(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->mapper->handleRefundCreated($payload);
            return response()->json(['message' => 'Refund processed'], 200);
        } catch (Exception $e) {
            report($e);
            return response()->json(['message' => 'Accepted'], 200);
        }
    }

    private function authenticate(Request $request): bool
    {
        $hmacHeader = $request->header('X-Shopify-Hmac-SHA256', '');
        $rawBody    = $request->getContent();

        return $this->verifier->verify($rawBody, $hmacHeader);
    }
}
