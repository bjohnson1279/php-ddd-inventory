<?php

namespace InventoryApp\Infrastructure\Integration\Shopify;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Resolves the mapping between Shopify's location IDs and our internal LocationIds,
 * and between SKUs and Shopify's inventory_item_ids.
 *
 * Data is loaded from the `shopify_location_mappings` and `shopify_sku_mappings`
 * tables, with an in-memory cache to avoid repeated queries within a request.
 */
class ShopifyMappingRepository
{
    /** @var array<string, string> shopify_location_id => our_location_id */
    private array $locationCache = [];

    /** @var array<string, string> sku => shopify_inventory_item_id */
    private array $skuCache = [];

    /**
     * Resolve a Shopify location_id to our internal LocationId string.
     * Returns null if no mapping is registered.
     */
    public function findLocationId(string $shopifyLocationId): ?string
    {
        if (!array_key_exists($shopifyLocationId, $this->locationCache)) {
            $row = DB::table('shopify_location_mappings')
                ->where('shopify_location_id', $shopifyLocationId)
                ->first(['our_location_id']);

            $this->locationCache[$shopifyLocationId] = $row?->our_location_id;
        }

        return $this->locationCache[$shopifyLocationId] ?: null;
    }

    /**
     * Resolve one of our internal LocationId strings to the Shopify location_id
     * needed for outbound inventory_levels/set API calls.
     * Returns null if no mapping exists.
     */
    public function findShopifyLocationId(string $ourLocationId): ?string
    {
        $cacheKey = 'reverse_' . $ourLocationId;
        if (!array_key_exists($cacheKey, $this->locationCache)) {
            $row = DB::table('shopify_location_mappings')
                ->where('our_location_id', $ourLocationId)
                ->first(['shopify_location_id']);

            $this->locationCache[$cacheKey] = $row?->shopify_location_id;
        }

        return $this->locationCache[$cacheKey] ?: null;
    }

    /**
     * Resolve one of our SKUs to the Shopify inventory_item_id needed for
     * the outbound inventory_levels/set API call.
     * Returns null if the SKU has never been synced to Shopify.
     */
    public function findShopifyInventoryItemId(string $sku): ?string
    {
        if (!array_key_exists($sku, $this->skuCache)) {
            $row = DB::table('shopify_sku_mappings')
                ->where('sku', $sku)
                ->first(['shopify_inventory_item_id']);

            $this->skuCache[$sku] = $row?->shopify_inventory_item_id;
        }

        return $this->skuCache[$sku] ?: null;
    }

    /**
     * Persist a SKU → Shopify inventory_item_id mapping (e.g. after product sync).
     */
    public function saveSkuMapping(string $sku, string $shopifyInventoryItemId): void
    {
        DB::table('shopify_sku_mappings')->updateOrInsert(
            ['sku'                        => $sku],
            ['shopify_inventory_item_id'  => $shopifyInventoryItemId]
        );

        unset($this->skuCache[$sku]);
    }
}
