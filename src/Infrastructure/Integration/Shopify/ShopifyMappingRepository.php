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
     * Preload mappings for a set of SKUs to prevent N+1 queries.
     *
     * @param string[] $skus
     */
    public function preloadShopifyInventoryItemIds(array $skus): void
    {
        if (empty($skus)) {
            return;
        }

        $missing = [];
        foreach ($skus as $sku) {
            if ($sku !== '' && !array_key_exists($sku, $this->skuCache)) {
                $missing[] = $sku;
            }
        }

        if (empty($missing)) {
            return;
        }

        $missing = array_unique($missing);
        $rows = DB::table('shopify_sku_mappings')
            ->whereIn('sku', $missing)
            ->get(['sku', 'shopify_inventory_item_id']);

        // Mark missing as null initially, so we cache negative lookups too
        foreach ($missing as $sku) {
            $this->skuCache[$sku] = null;
        }

        foreach ($rows as $row) {
            $this->skuCache[$row->sku] = $row->shopify_inventory_item_id;
        }
    }

    /**
     * Preload mappings for a set of Shopify location_ids to prevent N+1 queries.
     *
     * @param string[] $shopifyLocationIds
     */
    public function preloadShopifyLocationIds(array $shopifyLocationIds): void
    {
        if (empty($shopifyLocationIds)) {
            return;
        }

        $missing = [];
        foreach ($shopifyLocationIds as $id) {
            if ($id !== '' && !array_key_exists($id, $this->locationCache)) {
                $missing[] = $id;
            }
        }

        if (empty($missing)) {
            return;
        }

        $missing = array_unique($missing);
        $rows = DB::table('shopify_location_mappings')
            ->whereIn('shopify_location_id', $missing)
            ->get(['shopify_location_id', 'our_location_id']);

        // Mark missing as null initially, so we cache negative lookups too
        foreach ($missing as $id) {
            $this->locationCache[$id] = null;
        }

        foreach ($rows as $row) {
            $this->locationCache[$row->shopify_location_id] = $row->our_location_id;
        }
    }

    /**
     * Preload mappings for an array of our internal LocationIds to avoid N+1 queries.
     *
     * @param array<string> $ourLocationIds
     */
    public function preloadReverseLocationIds(array $ourLocationIds): void
    {
        $missingIds = [];
        foreach ($ourLocationIds as $id) {
            $cacheKey = 'reverse_' . $id;
            if (!array_key_exists($cacheKey, $this->locationCache)) {
                $missingIds[] = $id;
            }
        }

        if (empty($missingIds)) {
            return;
        }

        foreach (array_chunk($missingIds, 500) as $chunk) {
            $rows = DB::table('shopify_location_mappings')
                ->whereIn('our_location_id', $chunk)
                ->get(['shopify_location_id', 'our_location_id']);

            foreach ($rows as $row) {
                $cacheKey = 'reverse_' . $row->our_location_id;
                $this->locationCache[$cacheKey] = $row->shopify_location_id;
            }
        }

        // Cache negative lookups
        foreach ($missingIds as $id) {
            $cacheKey = 'reverse_' . $id;
            if (!array_key_exists($cacheKey, $this->locationCache)) {
                $this->locationCache[$cacheKey] = null;
            }
        }
    }

    /**
     * Preload mappings for an array of our SKUs to avoid N+1 queries.
     *
     * @param array<string> $skus
     */
    public function preloadShopifyInventoryItemIds(array $skus): void
    {
        $missingIds = [];
        foreach ($skus as $sku) {
            if (!array_key_exists($sku, $this->skuCache)) {
                $missingIds[] = $sku;
            }
        }

        if (empty($missingIds)) {
            return;
        }

        foreach (array_chunk($missingIds, 500) as $chunk) {
            $rows = DB::table('shopify_sku_mappings')
                ->whereIn('sku', $chunk)
                ->get(['sku', 'shopify_inventory_item_id']);

            foreach ($rows as $row) {
                $this->skuCache[$row->sku] = $row->shopify_inventory_item_id;
            }
        }

        // Cache negative lookups
        foreach ($missingIds as $sku) {
            if (!array_key_exists($sku, $this->skuCache)) {
                $this->skuCache[$sku] = null;
            }
        }
    }

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
