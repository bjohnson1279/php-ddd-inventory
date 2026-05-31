<?php

namespace InventoryApp\Application\Catalog\Listeners;

use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Domain\Shared\Events\QueuedListenerInterface;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use Illuminate\Database\Capsule\Manager as DB;

class SyncCatalogToShopify implements QueuedListenerInterface
{
    private ShopifyInventorySync $sync;
    private ShopifyMappingRepository $mappings;

    public function __construct(
        ShopifyInventorySync $sync,
        ShopifyMappingRepository $mappings
    ) {
        $this->sync = $sync;
        $this->mappings = $mappings;
    }

    public function handle(VariantAddedToCatalog $event): void
    {
        $sku = $event->getSku()->getValue();

        // 1. Check if mapping already exists
        if ($this->mappings->findShopifyInventoryItemId($sku) !== null) {
            return; // Already mapped, skip
        }

        // 2. Fetch the catalog variant price from the database capsule
        $variant = DB::table('catalog_variants')
            ->where('sku', $sku)
            ->first();

        $price = $variant ? (float)$variant->price : 10.00;

        try {
            // 3. Create the product and variant on Shopify
            $result = $this->sync->createProduct(
                $event->getProductName(),
                $sku,
                $price,
                $event->getDepartment()->getValue()
            );

            // 4. Register the new inventory item ID mapping
            $this->mappings->saveSkuMapping($sku, $result['shopify_inventory_item_id']);

            error_log("Successfully synchronized SKU {$sku} outbound to Shopify Inventory Item ID {$result['shopify_inventory_item_id']}");
        } catch (\Throwable $e) {
            error_log("Shopify catalog outbound sync failed for SKU {$sku}: " . $e->getMessage());
            // Rethrow so the queue worker retries this task
            throw $e;
        }
    }
}
