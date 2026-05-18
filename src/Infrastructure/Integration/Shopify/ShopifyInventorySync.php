<?php

namespace InventoryApp\Infrastructure\Integration\Shopify;

use InventoryApp\Domain\Shared\Events\DomainEvent;

/**
 * Shopify Admin REST API client for pushing inventory level updates outbound.
 *
 * Triggered by our domain events (e.g. StockReceived, SaleProcessed) to keep
 * the Shopify storefront stock count in sync with our system of record.
 *
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
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Shopify-Access-Token: ' . $this->accessToken,
            ],
        ]);

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
}
