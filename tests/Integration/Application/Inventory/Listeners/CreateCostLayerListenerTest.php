<?php

namespace Tests\Integration\Application\Inventory\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\Listeners\CreateCostLayerListener;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\OpeningBalancePosted;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use Illuminate\Database\Capsule\Manager as DB;
use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../../../bootstrap.php';

/** @group integration */
class CreateCostLayerListenerTest extends TestCase
{
    private $costLayerRepo;
    private $listener;

    protected function setUp(): void
    {
        DB::table('catalog_variants')->delete();
        DB::table('catalog_products')->delete();
        DB::table('inventory_cost_layers')->delete();

        // Ensure price cache is empty between tests
        $reflection = new \ReflectionClass(CreateCostLayerListener::class);
        $property = $reflection->getProperty('priceCache');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $this->costLayerRepo = $this->createMock(CostLayerRepositoryInterface::class);
        $this->listener = new CreateCostLayerListener($this->costLayerRepo, 'test-tenant');
    }

    public function test_preloadPrices_fetches_from_db(): void
    {
        // Setup catalog_variants in DB
        DB::table('catalog_products')->insert([
            'id' => Uuid::uuid4()->toString(),
            'name' => 'Test Product',
            'department' => 'DEPT',
            'tenant_id' => 'test-tenant'
        ]);
        $productId = DB::table('catalog_products')->first()->id;

        DB::table('catalog_variants')->insert([
            'id' => Uuid::uuid4()->toString(),
            'product_id' => $productId,
            'sku' => 'CACHED-SKU',
            'price' => 25.50
        ]);

        $this->listener->preloadPrices(['CACHED-SKU', 'UNCACHED-SKU']);

        $reflection = new \ReflectionClass(CreateCostLayerListener::class);
        $property = $reflection->getProperty('priceCache');
        $property->setAccessible(true);
        $cache = $property->getValue();

        $this->assertEquals(25.50, $cache['CACHED-SKU']);
        $this->assertEquals(10.00, $cache['UNCACHED-SKU']);
    }

    public function test_handleStockReceived_creates_layer_with_cached_price(): void
    {
        // Populate cache manually to avoid DB dependency in this test
        $reflection = new \ReflectionClass(CreateCostLayerListener::class);
        $property = $reflection->getProperty('priceCache');
        $property->setAccessible(true);
        $property->setValue(null, ['SKU-1' => 15.00]);

        $event = new StockReceived(
            new SKU('SKU-1'),
            new LocationId('LOC-1'),
            100,
            'PO-123',
            new \DateTimeImmutable()
        );

        $this->costLayerRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (InventoryCostLayer $layer) use ($event) {
                return $layer->variantId === 'SKU-1'
                    && $layer->tenantId === 'test-tenant'
                    && $layer->originalQuantity === 100
                    && $layer->unitCostCents === 1500
                    && $layer->purchaseOrderId === 'PO-123';
            }));

        $this->listener->handleStockReceived($event);
    }

    public function test_handleStockReceived_skips_when_flag_is_true(): void
    {
        $event = new StockReceived(
            new SKU('SKU-1'),
            new LocationId('LOC-1'),
            100,
            'PO-123',
            new \DateTimeImmutable(),
            true // skipCostLayerCreation
        );

        $this->costLayerRepo->expects($this->never())->method('save');

        $this->listener->handleStockReceived($event);
    }

    public function test_handleOpeningBalancePosted_creates_layer(): void
    {
        $date = new \DateTimeImmutable();
        $event = new OpeningBalancePosted(
            'OB-999',
            'VAR-1',
            50,
            1250, // 12.50
            'LOC-1',
            $date,
            $date
        );

        $this->costLayerRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (InventoryCostLayer $layer) use ($event) {
                return $layer->variantId === 'VAR-1'
                    && $layer->tenantId === 'test-tenant'
                    && $layer->originalQuantity === 50
                    && $layer->unitCostCents === 1250
                    && $layer->purchaseOrderId === 'ONBOARDING-OB-999';
            }));

        $this->listener->handleOpeningBalancePosted($event);
    }
}
