<?php

namespace Tests\Unit\Application\Catalog\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Catalog\Listeners\SyncCatalogToShopify;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use Illuminate\Database\Capsule\Manager as DB;

class SyncCatalogToShopifyTest extends TestCase
{
    private $sync;
    private $mappings;
    private $listener;

    protected function setUp(): void
    {
        $this->sync = $this->createMock(ShopifyInventorySync::class);
        $this->mappings = $this->createMock(ShopifyMappingRepository::class);
        $this->listener = new SyncCatalogToShopify($this->sync, $this->mappings);

        // Setup in-memory SQLite Capsule
        $capsule = new DB();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        DB::schema()->create('catalog_variants', function ($table) {
            $table->string('sku')->primary();
            $table->decimal('price', 10, 2);
        });
    }

    public function testHandleSyncsCatalogToShopify(): void
    {
        // 1. Seed variant in DB
        DB::table('catalog_variants')->insert([
            'sku' => 'CAT-SKU-1',
            'price' => 19.99
        ]);

        // 2. Create Event
        $event = new VariantAddedToCatalog(
            'prod-123',
            'Test Catalog Product',
            new Department('GEN'),
            new SKU('CAT-SKU-1')
        );

        // 3. Setup mapping mocks
        $this->mappings->method('findShopifyInventoryItemId')->with('CAT-SKU-1')->willReturn(null);

        // 4. Setup mock for Shopify sync
        $this->sync->expects($this->once())
            ->method('createProduct')
            ->with('Test Catalog Product', 'CAT-SKU-1', 19.99, 'GEN')
            ->willReturn([
                'shopify_product_id' => 'shp-prod-999',
                'shopify_variant_id' => 'shp-var-999',
                'shopify_inventory_item_id' => 'shp-inv-999'
            ]);

        // 5. Expect saveSkuMapping to be called
        $this->mappings->expects($this->once())
            ->method('saveSkuMapping')
            ->with('CAT-SKU-1', 'shp-inv-999');

        $this->listener->handle($event);
    }

    public function testHandleSkipsWhenMappingExists(): void
    {
        $event = new VariantAddedToCatalog(
            'prod-123',
            'Test Catalog Product',
            new Department('GEN'),
            new SKU('CAT-SKU-1')
        );

        $this->mappings->method('findShopifyInventoryItemId')->with('CAT-SKU-1')->willReturn('shp-inv-999');

        $this->sync->expects($this->never())->method('createProduct');

        $this->listener->handle($event);
    }

    public function testHandlePropagatesFailureAndDoesNotSaveMapping(): void
    {
        DB::table('catalog_variants')->insert([
            'sku' => 'CAT-SKU-1',
            'price' => 19.99
        ]);

        $event = new VariantAddedToCatalog(
            'prod-123',
            'Test Catalog Product',
            new Department('GEN'),
            new SKU('CAT-SKU-1')
        );

        $this->mappings->method('findShopifyInventoryItemId')->with('CAT-SKU-1')->willReturn(null);

        $this->sync->method('createProduct')->willThrowException(new \RuntimeException('Shopify Connection Error'));

        $this->mappings->expects($this->never())->method('saveSkuMapping');

        $this->expectException(\RuntimeException::class);
        $this->listener->handle($event);
    }
}
