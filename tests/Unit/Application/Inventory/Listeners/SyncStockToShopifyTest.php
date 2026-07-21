<?php

namespace Tests\Unit\Application\Inventory\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\Listeners\SyncStockToShopify;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;

class SyncStockToShopifyTest extends TestCase
{
    private $sync;
    private $mappings;
    private $productRepo;
    private $listener;

    protected function setUp(): void
    {
        // Setup in-memory SQLite Capsule
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        require_once __DIR__ . '/../../../../../src/Infrastructure/Persistence/sqlite_setup.php';
        \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema($capsule->getConnection());

        $this->sync = $this->createMock(ShopifyInventorySync::class);
        $this->mappings = $this->createMock(ShopifyMappingRepository::class);
        $this->productRepo = $this->createMock(ProductRepositoryInterface::class);
        $this->listener = new SyncStockToShopify($this->sync, $this->mappings, $this->productRepo);
    }

    public function testHandleSyncsStockToShopify(): void
    {
        // 1. Create a mock event
        $event = new class {
            public function getSku(): SKU { return new SKU('SKU-1'); }
            public function getLocationId(): LocationId { return new LocationId('LOC-1'); }
        };

        // 2. Setup mapping mocks
        $this->mappings->method('findShopifyInventoryItemId')->with('SKU-1')->willReturn('sh_inv_123');
        $this->mappings->method('findShopifyLocationId')->with('LOC-1')->willReturn('sh_loc_456');

        // 3. Setup product mock
        $product = Product::create(
            'p-1', new SKU('SKU-1'), 'Test', new Department('D1'), new LocationId('LOC-1'), new Quantity(25)
        );
        $this->productRepo->method('findBySku')->willReturn($product);

        // 4. Expect sync call
        $this->sync->expects($this->once())
            ->method('setInventoryLevel')
            ->with('sh_inv_123', 'sh_loc_456', 25);

        $this->listener->handle($event);
    }

    public function testHandleSkipsWhenNoMappingExists(): void
    {
        $event = new class {
            public function getSku(): SKU { return new SKU('SKU-1'); }
            public function getLocationId(): LocationId { return new LocationId('LOC-1'); }
        };

        $this->mappings->method('findShopifyInventoryItemId')->willReturn(null);

        $this->sync->expects($this->never())->method('setInventoryLevel');

        $this->listener->handle($event);
    }

    public function testHandleGracefullyHandlesShopifyFailure(): void
    {
        $event = new class {
            public function getSku(): SKU { return new SKU('SKU-1'); }
            public function getLocationId(): LocationId { return new LocationId('LOC-1'); }
        };

        $this->mappings->method('findShopifyInventoryItemId')->willReturn('sh_inv_123');
        $this->mappings->method('findShopifyLocationId')->willReturn('sh_loc_456');

        $product = Product::create(
            'p-1', new SKU('SKU-1'), 'Test', new Department('D1'), new LocationId('LOC-1'), new Quantity(25)
        );
        $this->productRepo->method('findBySku')->willReturn($product);

        $this->sync->method('setInventoryLevel')->willThrowException(new \RuntimeException('Shopify API Down'));

        // Should not throw exception
        $this->listener->handle($event);
        $this->assertTrue(true);
    }
}
