<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\ServiceContainer;
use InventoryApp\Application\Inventory\UseCases\ProcessSale;
use InventoryApp\Application\Inventory\UseCases\ProcessReturn;
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
            if (!empty($secret)) {
                $calculatedHmac = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
                if (!hash_equals($hmacHeader, $calculatedHmac)) {
                    return new Response(['error' => 'HMAC verification failed'], 401);
                }
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
                
                foreach ($lineItems as $item) {
                    $sku = $item['sku'] ?? '';
                    $qty = (int)($item['quantity'] ?? 0);
                    
                    if (empty($sku) || $qty <= 0) {
                        continue;
                    }

                    $useCase = new ProcessSale($productRepo, $dispatcher);
                    $useCase->execute($sku, $locationId, $qty, $orderId);
                }
                
                return new Response(['message' => 'Order webhook processed, stock decremented'], 200);
            }

            if ($topic === 'orders/cancelled') {
                $orderId = 'shopify-order-' . ($data['id'] ?? 'unknown');
                $lineItems = $data['line_items'] ?? [];
                
                foreach ($lineItems as $item) {
                    $sku = $item['sku'] ?? '';
                    $qty = (int)($item['quantity'] ?? 0);
                    
                    if (empty($sku) || $qty <= 0) {
                        continue;
                    }

                    $useCase = new ProcessReturn($productRepo, $dispatcher);
                    $useCase->execute($sku, $locationId, $qty, 'new', $orderId);
                }
                
                return new Response(['message' => 'Cancellation webhook processed, stock restocked'], 200);
            }

            if ($topic === 'refunds/create') {
                $orderId = 'shopify-order-' . ($data['order_id'] ?? 'unknown');
                $refundLineItems = $data['refund_line_items'] ?? [];
                
                foreach ($refundLineItems as $rItem) {
                    $item = $rItem['line_item'] ?? [];
                    $sku = $item['sku'] ?? '';
                    $qty = (int)($rItem['quantity'] ?? 0);
                    
                    if (empty($sku) || $qty <= 0) {
                        continue;
                    }

                    $useCase = new ProcessReturn($productRepo, $dispatcher);
                    $useCase->execute($sku, $locationId, $qty, 'new', $orderId);
                }
                
                return new Response(['message' => 'Refund webhook processed, stock restocked'], 200);
            }

            return new Response(['message' => 'Webhook topic not supported, ignored'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
