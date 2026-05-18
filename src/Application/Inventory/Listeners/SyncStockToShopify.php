<?php

namespace InventoryApp\Application\Inventory\Listeners;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;

/**
 * Listens to any of our stock-mutation domain events and pushes the updated
 * inventory level to Shopify's inventory_levels/set API.
 *
 * Supported events (all carry a `getSku()` and `getLocationId()` method):
 *   - StockReceived, SaleProcessed, StockDispatched, ReturnProcessed, StockReconciled
 *
 * Wire via EventDispatcher::subscribe() in your service container bootstrap:
 *
 *   foreach ([StockReceived::class, SaleProcessed::class, ...] as $event) {
 *       $dispatcher->subscribe($event, [$listener, 'handle']);
 *   }
 *
 * NOTE: This listener is intentionally fault-tolerant — a Shopify API failure
 * must never roll back the domain operation that triggered it. Wrap in a
 * try/catch and push failures to a retry queue in production.
 */
class SyncStockToShopify
{
    private ShopifyInventorySync $sync;
    private ShopifyMappingRepository $mappings;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        ShopifyInventorySync $sync,
        ShopifyMappingRepository $mappings,
        ProductRepositoryInterface $productRepository
    ) {
        $this->sync              = $sync;
        $this->mappings          = $mappings;
        $this->productRepository = $productRepository;
    }

    /**
     * @param object $event  Any stock-mutation event carrying getSku() + getLocationId()
     */
    public function handle(object $event): void
    {
        // Guard: only process events that expose the two identifiers we need
        if (!method_exists($event, 'getSku') || !method_exists($event, 'getLocationId')) {
            return;
        }

        $sku        = $event->getSku()->getValue();
        $locationId = $event->getLocationId()->getValue();

        // Look up the Shopify-specific IDs from our mapping tables
        $shopifyInventoryItemId = $this->mappings->findShopifyInventoryItemId($sku);
        $shopifyLocationId      = $this->mappings->findShopifyLocationId($locationId);

        // Skip if this SKU or location hasn't been mapped to Shopify yet
        if ($shopifyInventoryItemId === null || $shopifyLocationId === null) {
            return;
        }

        // Fetch the current authoritative stock quantity for this location
        $product  = $this->productRepository->findBySku(new SKU($sku));
        if (!$product) {
            return;
        }

        $currentQty = $product
            ->getStockAt(new \InventoryApp\Domain\Inventory\ValueObjects\LocationId($locationId))
            ->getStockQuantity()
            ->getValue();

        // Push to Shopify — catch failures so the domain operation is never rolled back
        try {
            $this->sync->setInventoryLevel($shopifyInventoryItemId, $shopifyLocationId, $currentQty);
        } catch (\Throwable $e) {
            // TODO: push to retry queue (e.g., a `shopify_sync_failures` table or queue job)
            error_log("Shopify sync failed for SKU {$sku}: " . $e->getMessage());
        }
    }
}
