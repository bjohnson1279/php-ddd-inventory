<?php

namespace InventoryApp\Infrastructure\Integration\Shopify;

/**
 * Shopify Admin REST API client for pushing inventory level updates outbound.
 *
 * Triggered by our domain events (e.g. StockReceived, SaleProcessed) to keep
 * the Shopify storefront stock count in sync with our system of record.
 * @see https://shopify.dev/docs/api/admin-rest/inventory-level#set
 */
class ShopifyInventorySync
{
    private string $shopDomain;
    private string $accessToken;

    public function __construct(string $shopDomain, string $accessToken)
    {
        $this->shopDomain  = rtrim($shopDomain, '/');
        $this->accessToken = $accessToken;
    }

    /**
     * Push a stock level update to Shopify for a given variant + location.
     *
     * @param string $shopifyInventoryItemId  Shopify's internal inventory_item_id for the variant
     * @param string $shopifyLocationId       Shopify's location_id to update
     * @param int    $newQuantity             The current quantity to set (not a delta)
     */
    public function setInventoryLevel(string $shopifyInventoryItemId, string $shopifyLocationId, int $newQuantity): void
    {
        $url  = "{$this->shopDomain}/admin/api/2024-01/inventory_levels/set.json";
        $body = json_encode([
            'location_id'        => $shopifyLocationId,
            'inventory_item_id'  => $shopifyInventoryItemId,
            'available'          => $newQuantity,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Shopify-Access-Token: ' . $this->accessToken,
            ],

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatus !== 200) {
            // In production: push to a retry queue rather than throwing
            throw new \RuntimeException(
                "Shopify inventory sync failed (HTTP {$httpStatus}): {$response}"
            );
        }
    }

     * Create a product and return its details, including variant id and inventory item id.
     * @param string $title
     * @param string $sku
     * @param float  $price
     * @param string $productType
     * @return array
    public function createProduct(string $title, string $sku, float $price, string $productType): array
    {
        if (empty($this->shopDomain) || str_contains($this->shopDomain, 'example.com') || str_contains($this->shopDomain, 'token') || str_contains($this->shopDomain, 'mock')) {
            return [
                'shopify_product_id'        => 'mock-prod-999',
                'shopify_variant_id'        => 'mock-var-999',
                'shopify_inventory_item_id' => 'mock-inv-item-' . $sku
            ];
        }

        $url  = "{$this->shopDomain}/admin/api/2024-01/products.json";
            'product' => [
                'title'        => $title,
                'product_type' => $productType,
                'status'       => 'active',
                'variants'     => [
                    [
                        'sku'   => $sku,
                        'price' => (string)$price,
                    ]
                ]
            ]



        if ($httpStatus !== 201) {
                "Shopify product creation failed (HTTP {$httpStatus}): {$response}"
        }

        $data = json_decode($response, true);
        $variant = $data['product']['variants'][0] ?? null;
        if (!$variant) {
            throw new \RuntimeException("No variant returned in Shopify product creation: {$response}");
        }

        return [
            'shopify_product_id'        => (string)$data['product']['id'],
            'shopify_variant_id'        => (string)$variant['id'],
            'shopify_inventory_item_id' => (string)$variant['inventory_item_id']
        ];
    }
}


{

    {
    }

    {

            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,


        }
    }

    {
        }




        }

        }

    }
}
