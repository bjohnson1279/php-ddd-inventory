<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Application\Ports\LabelResult;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use DateTimeImmutable;
use Exception;

class PurchaseShippingLabelTest extends TestCase
{
    private $shipmentRepo;
    private $carrierService;
    private $productRepo;
    private $ledgerRepo;
    private $journalRepo;
    private $outboxRepo;
    private $costLayerRepo;

    protected function setUp(): void
    {
        $this->shipmentRepo = $this->createMock(ShipmentRepositoryInterface::class);
        $this->carrierService = $this->createMock(CarrierServiceInterface::class);
        $this->productRepo = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepo = $this->createMock(LedgerRepositoryInterface::class);
        $this->journalRepo = $this->createMock(JournalRepositoryInterface::class);
        $this->outboxRepo = $this->createMock(OutboxRepositoryInterface::class);
        $this->costLayerRepo = $this->createMock(CostLayerRepositoryInterface::class);

        $_SERVER['auth.user_id'] = 'user-123';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.user_id']);
    }

    public function testExecuteSuccess(): void
    {
        $sku = 'TEST-SKU';
        $quantity = 2;
        $destination = '123 Main St';
        $carrier = 'UPS';
        $locationId = 'LOC-123';
        $tenantId = 'tenant-1';

        // Setup Product with stock
        $product = new Product('p1', new SKU($sku), 'Test Prod', new Department('TEST'));
        $product->getStockAt(new LocationId($locationId))->addStock(new Quantity(5), new Condition(Condition::NEW));

        $this->productRepo->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(fn($s) => $s->getValue() === $sku))
            ->willReturn($product);

        $this->productRepo->expects($this->once())
            ->method('save')
            ->with($product);

        // Setup Carrier Service
        $labelResult = new LabelResult('TRACKING-123', 'http://label.url', 1500);
        $this->carrierService->expects($this->once())
            ->method('generateLabel')
            ->with($sku, $quantity, $destination, $carrier)
            ->willReturn($labelResult);

        // Expect Ledger Entry
        $this->ledgerRepo->expects($this->once())->method('append');

        // Setup Cost Layers
        $layer1 = new InventoryCostLayer('layer-1', $sku, $tenantId, 1, 1000, new DateTimeImmutable());
        $layer2 = new InventoryCostLayer('layer-2', $sku, $tenantId, 5, 1200, new DateTimeImmutable());

        $this->costLayerRepo->expects($this->once())
            ->method('getActiveLayers')
            ->with($sku, 'expiration_date ASC')
            ->willReturn([$layer1, $layer2]);

        $this->costLayerRepo->expects($this->once())
            ->method('saveBatch')
            ->with($this->callback(function ($layers) use ($layer1, $layer2) {
                return count($layers) === 2 && $layers[0] === $layer1 && $layers[1] === $layer2;
            }));

        // Expect Shipment, Journal, and Outbox saved
        $this->shipmentRepo->expects($this->once())->method('save');
        $this->journalRepo->expects($this->once())->method('save');
        $this->outboxRepo->expects($this->once())->method('save');

        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepo,
            $this->carrierService,
            $this->productRepo,
            $this->ledgerRepo,
            $this->journalRepo,
            $this->outboxRepo,
            $this->costLayerRepo
        );

        $result = $useCase->execute($sku, $quantity, $destination, $carrier, $locationId, $tenantId);

        $this->assertEquals('TRACKING-123', $result->trackingNumber);
        $this->assertEquals('http://label.url', $result->labelUrl);
        $this->assertEquals(1500, $result->rateCents);
        $this->assertEquals(3, $product->getStockAt(new LocationId($locationId))->getStockQuantity()->getValue());
    }

    public function testExecuteThrowsExceptionForMissingParameters(): void
    {
        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepo, $this->carrierService, $this->productRepo,
            $this->ledgerRepo, $this->journalRepo, $this->outboxRepo, $this->costLayerRepo
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required parameters for shipping label purchase.");

        $useCase->execute('', 1, 'address', 'carrier', 'LOC-1', 'tenant-1');
    }

    public function testExecuteThrowsExceptionForNegativeQuantity(): void
    {
        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepo, $this->carrierService, $this->productRepo,
            $this->ledgerRepo, $this->journalRepo, $this->outboxRepo, $this->costLayerRepo
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required parameters for shipping label purchase.");

        $useCase->execute('SKU', -1, 'address', 'carrier', 'LOC-1', 'tenant-1');
    }

    public function testExecuteThrowsExceptionWhenProductNotFound(): void
    {
        $this->productRepo->expects($this->once())
            ->method('findBySku')
            ->willReturn(null);

        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepo, $this->carrierService, $this->productRepo,
            $this->ledgerRepo, $this->journalRepo, $this->outboxRepo, $this->costLayerRepo
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Inventory item not found for SKU TEST-SKU at location LOC-1.");

        $useCase->execute('TEST-SKU', 1, 'address', 'carrier', 'LOC-1', 'tenant-1');
    }

    public function testExecuteThrowsExceptionForInsufficientStock(): void
    {
        $sku = 'TEST-SKU';
        $locationId = 'LOC-123';
        $product = new Product('p1', new SKU($sku), 'Test Prod', new Department('TEST'));
        // 0 stock initially

        $this->productRepo->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepo, $this->carrierService, $this->productRepo,
            $this->ledgerRepo, $this->journalRepo, $this->outboxRepo, $this->costLayerRepo
        );

        $this->expectException(InsufficientStockException::class);

        $useCase->execute($sku, 2, 'address', 'carrier', $locationId, 'tenant-1');
    }

    public function testExecuteThrowsExceptionForInsufficientCostLayers(): void
    {
        $sku = 'TEST-SKU';
        $locationId = 'LOC-123';
        $quantity = 5;
        $product = new Product('p1', new SKU($sku), 'Test Prod', new Department('TEST'));
        $product->getStockAt(new LocationId($locationId))->addStock(new Quantity(10), new Condition(Condition::NEW));

        $this->productRepo->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $labelResult = new LabelResult('TRACKING-123', 'http://label.url', 1500);
        $this->carrierService->expects($this->once())
            ->method('generateLabel')
            ->willReturn($labelResult);

        // Return only enough for 2, but need 5
        $layer1 = new InventoryCostLayer('layer-1', $sku, 'tenant-1', 2, 1000, new DateTimeImmutable());
        $this->costLayerRepo->expects($this->once())
            ->method('getActiveLayers')
            ->willReturn([$layer1]);

        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepo, $this->carrierService, $this->productRepo,
            $this->ledgerRepo, $this->journalRepo, $this->outboxRepo, $this->costLayerRepo
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient cost layers to cover dispatch quantity of 5 for SKU TEST-SKU");

        $useCase->execute($sku, $quantity, 'address', 'carrier', $locationId, 'tenant-1');
    }
}
