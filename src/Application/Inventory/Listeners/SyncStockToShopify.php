<?php

namespace InventoryApp\Application\Inventory\Listeners;

use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;

use InventoryApp\Domain\Shared\Events\QueuedListenerInterface;
use Illuminate\Database\Capsule\Manager as DB;

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
class SyncStockToShopify implements QueuedListenerInterface
{
    private static array $productCache = [];
    private static array $itemCache = [];
    private static array $locationCache = [];
    private static bool $isBatching = false;

    private ShopifyInventorySync $sync;
    private ShopifyMappingRepository $mappings;
    private ProductRepositoryInterface $productRepository;

    /**
     * @param \InventoryApp\Domain\Inventory\Entities\Product[] $products
     */
    public static function beginBatch(array $products): void
    {
        self::$isBatching = true;
        self::$productCache = [];
        self::$itemCache = [];
        self::$locationCache = [];

        if (empty($products)) {
            return;
        }

        $skus = [];
        $locationIds = [];

        foreach ($products as $product) {
            $tenantId = method_exists($product, 'getTenantId') ? $product->getTenantId() : 'system';
            $sku = $product->getSku()->getValue();
            self::$productCache[$tenantId . ':' . $sku] = $product;

            $skus[] = $sku;
            foreach ($product->getLocationStocks() as $locationStock) {
                $locationIds[] = $locationStock->getLocationId()->getValue();
            }
        }

        $uniqueSkus = array_unique($skus);
        foreach (array_chunk($uniqueSkus, 500) as $chunk) {
            try {
                $rows = DB::table('shopify_sku_mappings')
                    ->whereIn('sku', $chunk)
                    ->get(['sku', 'shopify_inventory_item_id']);
                foreach ($rows as $row) {
                    self::$itemCache[$row->sku] = $row->shopify_inventory_item_id;
                }
            } catch (\Throwable $e) {
                if (DB::connection()->getDriverName() === 'sqlite' && str_contains($e->getMessage(), 'no such table')) {
                    // Ignore missing table during isolated SQLite tests
                } else {
                    throw $e;
                }
            }
            foreach ($chunk as $sku) {
                if (!array_key_exists($sku, self::$itemCache)) {
                    self::$itemCache[$sku] = null;
                }
            }
        }

        $uniqueLocationIds = array_unique($locationIds);
        foreach (array_chunk($uniqueLocationIds, 500) as $chunk) {
            try {
                $rows = DB::table('shopify_location_mappings')
                    ->whereIn('our_location_id', $chunk)
                    ->get(['our_location_id', 'shopify_location_id']);
                foreach ($rows as $row) {
                    self::$locationCache[$row->our_location_id] = $row->shopify_location_id;
                }
            } catch (\Throwable $e) {
                if (DB::connection()->getDriverName() === 'sqlite' && str_contains($e->getMessage(), 'no such table')) {
                    // Ignore missing table during isolated SQLite tests
                } else {
                    throw $e;
                }
            }
            foreach ($chunk as $locId) {
                if (!array_key_exists($locId, self::$locationCache)) {
                    self::$locationCache[$locId] = null;
                }
            }
        }
    }

    public static function endBatch(): void
    {
        self::$isBatching = false;
        self::$productCache = [];
        self::$itemCache = [];
        self::$locationCache = [];
    }

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
        if (self::$isBatching) {
            $shopifyInventoryItemId = self::$itemCache[$sku] ?? null;
            $shopifyLocationId      = self::$locationCache[$locationId] ?? null;
        } else {
            $shopifyInventoryItemId = $this->mappings->findShopifyInventoryItemId($sku);
            $shopifyLocationId      = $this->mappings->findShopifyLocationId($locationId);
        }

        // Skip if this SKU or location hasn't been mapped to Shopify yet
        if ($shopifyInventoryItemId === null || $shopifyLocationId === null) {
            return;
        }

        // Fetch the current authoritative stock quantity for this location
        $product = null;
        if (self::$isBatching) {
            $tenantId = method_exists($this->productRepository, 'getTenantId')
                ? $this->productRepository->getTenantId()
                : 'system';
            $cacheKey = $tenantId . ':' . $sku;
            $product = self::$productCache[$cacheKey] ?? null;
        }

        if (!$product) {
            $product = $this->productRepository->findBySku(new SKU($sku));
        }

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
            error_log("Shopify sync failed for SKU {$sku}: " . $e->getMessage());

            $tenantId = method_exists($this->productRepository, 'getTenantId')
                ? $this->productRepository->getTenantId()
                : 'system';

            try {
                \Illuminate\Database\Capsule\Manager::table('shopify_sync_failures')->insert([
                    'id'          => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                    'tenant_id'   => $tenantId,
                    'sku'         => $sku,
                    'location_id' => $locationId,
                    'quantity'    => $currentQty,
                    'attempts'    => 1,
                    'last_error'  => $e->getMessage(),
                    'status'      => 'pending',
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                ]);
            } catch (\Throwable $dbEx) {
                error_log("Failed logging Shopify sync failure to DB: " . $dbEx->getMessage());
            }
        }
    }
}
