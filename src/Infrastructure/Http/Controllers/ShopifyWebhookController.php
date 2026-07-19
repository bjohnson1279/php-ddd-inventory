<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\ServiceContainer;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class ShopifyWebhookController
{
    public function handle(RequestInterface $request)
    {
        try {
            $tenantId = $request->query('tenant_id');
            if (empty($tenantId)) {
                return new Response(['error' => 'tenant_id query parameter is required'], 400);
            }

            $hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? $_SERVER['X-Shopify-Hmac-Sha256'] ?? '';
            $topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? $_SERVER['X-Shopify-Topic'] ?? '';
            $rawBody = file_get_contents('php://input');

            // HMAC validation
            $secret = getenv('SHOPIFY_WEBHOOK_SECRET');
            if (empty($secret)) {
                // Fail securely instead of bypassing the check
                return new Response(['error' => 'Webhook secret is not configured'], 500);
            }

            $calculatedHmac = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
            if (!hash_equals($hmacHeader, $calculatedHmac)) {
                return new Response(['error' => 'HMAC verification failed'], 401);
            }

            $data = json_decode($rawBody, true) ?: [];

            // Resolve default internal location mapping
            $locationMapping = \Illuminate\Database\Capsule\Manager::table('shopify_location_mappings')->first();
            $locationId = $locationMapping ? $locationMapping->our_location_id : 'LOC-STOREFRONT';

            $dispatcher = ServiceContainer::dispatcher();
            $productRepo = ServiceContainer::productRepo($tenantId);

            if ($topic === 'orders/create') {
                $orderId = 'shopify-order-' . ($data['id'] ?? 'unknown');
                $lineItems = $data['line_items'] ?? [];
                
                $this->processBatch($lineItems, $locationId, $orderId, 'sale', $productRepo, $dispatcher);
                
                return new Response(['message' => 'Order webhook processed, stock decremented'], 200);
            }

            if ($topic === 'orders/cancelled') {
                $orderId = 'shopify-order-' . ($data['id'] ?? 'unknown');
                $lineItems = $data['line_items'] ?? [];
                
                $this->processBatch($lineItems, $locationId, $orderId, 'return', $productRepo, $dispatcher);
                
                return new Response(['message' => 'Cancellation webhook processed, stock restocked'], 200);
            }

            if ($topic === 'refunds/create') {
                $orderId = 'shopify-order-' . ($data['order_id'] ?? 'unknown');
                $refundLineItems = $data['refund_line_items'] ?? [];
                
                // Extract line_items from refund payload
                $lineItems = [];
                foreach ($refundLineItems as $rItem) {
                    $item = $rItem['line_item'] ?? [];
                    $item['quantity'] = $rItem['quantity'] ?? 0;
                    $lineItems[] = $item;
                }

                $this->processBatch($lineItems, $locationId, $orderId, 'return', $productRepo, $dispatcher);
                
                return new Response(['message' => 'Refund webhook processed, stock restocked'], 200);
            }

            return new Response(['message' => 'Webhook topic not supported, ignored'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ShopifyWebhookController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 400); // 400 so Shopify won't retry
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function processBatch(array $items, string $locationValue, string $orderId, string $action, $productRepo, $dispatcher): void
    {
        $skusToProcess = [];
        $validItems = [];

        foreach ($items as $item) {
            $skuValue = $item['sku'] ?? '';
            $qty = (int)($item['quantity'] ?? 0);

            if (empty($skuValue) || $qty <= 0) {
                continue;
            }

            $skusToProcess[] = new SKU($skuValue);
            $validItems[] = ['sku' => $skuValue, 'qty' => $qty];
        }

        if (empty($validItems)) {
            return;
        }

        $productsBySku = $productRepo->findBySkus($skusToProcess);
        $productsToSave = [];
        $eventsToDispatch = [];
        $locationId = new LocationId($locationValue);
        $condition = new Condition('new');

        foreach ($validItems as $item) {
            $skuValue = $item['sku'];
            $qty = $item['qty'];

            if (!isset($productsBySku[$skuValue])) {
                throw new Exception("Product not found with SKU: " . $skuValue);
            }

            $product = $productsBySku[$skuValue];
            $quantity = new Quantity($qty);

            if ($action === 'sale') {
                $product->processSaleAt($locationId, $quantity, $orderId);
            } else if ($action === 'return') {
                $product->processReturnAt($locationId, $quantity, $condition, $orderId);
            }

            $productsToSave[$product->getId()] = $product;
        }

        if (!empty($productsToSave)) {
            $productRepo->saveAll(array_values($productsToSave));

            // Bolt optimization: Prevent N+1 queries during Shopify webhook batch processing.
            // We wrap the event dispatch loop with beginBatch/endBatch to leverage the static caching
            // in the SyncStockToShopify listener, which pre-loads mapping data in a single query.
            \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::beginBatch(array_values($productsToSave));
            try {
                foreach ($productsToSave as $product) {
                    foreach ($product->releaseEvents() as $event) {
                        $eventsToDispatch[] = $event;
                    }
                }

                foreach ($eventsToDispatch as $event) {
                    $dispatcher->dispatch($event);
                }
            } finally {
                \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::endBatch();
            }
        }
    }
}
