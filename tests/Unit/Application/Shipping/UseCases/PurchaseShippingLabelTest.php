<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Application\Ports\LabelResult;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;
use Exception;
use DateTimeImmutable;

class PurchaseShippingLabelTest extends TestCase
{
    private $shipmentRepository;
    private $carrierService;
    private $productRepository;
    private $ledgerRepository;
    private $journalRepository;
    private $outboxRepository;
    private $costLayerRepository;
    private $useCase;

    protected function setUp(): void
    {
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->carrierService = $this->createMock(CarrierServiceInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepository = $this->createMock(LedgerRepositoryInterface::class);
        $this->journalRepository = $this->createMock(JournalRepositoryInterface::class);
        $this->outboxRepository = $this->createMock(OutboxRepositoryInterface::class);
        $this->costLayerRepository = $this->createMock(CostLayerRepositoryInterface::class);

        $this->useCase = new PurchaseShippingLabel(
            $this->shipmentRepository,
            $this->carrierService,
            $this->productRepository,
            $this->ledgerRepository,
            $this->journalRepository,
            $this->outboxRepository,
            $this->costLayerRepository
        );
    }

    private function createProductWithStock(string $sku, string $locationId, int $quantity): Product
    {
        $product = Product::create(
            'prod_123',
            new SKU($sku),
            'Test Product',
            new Department('GENERAL'),
            new LocationId($locationId),
            new Quantity($quantity)
        return $product;
    }

    public function testExecuteSuccessfullyGeneratesLabelAndDispatchesStock()
    {
        $sku = 'TSHIRT-L-RED';
        $locationId = 'LOC-STOREFRONT';
        $tenantId = 'TENANT-123';

        $product = $this->createProductWithStock($sku, $locationId, 10);

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->with($this->equalTo(new SKU($sku)))
            ->willReturn($product);

        $labelResult = new LabelResult('TRACK-123', 'http://labels.com/123', 500);
        $this->carrierService->expects($this->once())
            ->method('generateLabel')
            ->with($sku, 2, '123 Main St', 'UPS')
            ->willReturn($labelResult);

            ->method('save')
            ->with($this->callback(function (Product $p) use ($locationId) {
                return $p->getStockAt(new LocationId($locationId))->getStockQuantity()->getValue() === 8;
            }));

        $this->ledgerRepository->expects($this->once())
            ->method('append');

        $costLayer = new InventoryCostLayer('layer-1', $sku, $tenantId, 5, 1000, new DateTimeImmutable());
        $this->costLayerRepository->expects($this->once())
            ->method('getActiveLayers')
            ->willReturn([$costLayer]);

            ->method('saveBatch');

        $this->shipmentRepository->expects($this->once())
            ->method('save');

        $this->journalRepository->expects($this->once())

        $this->outboxRepository->expects($this->once())

        $result = $this->useCase->execute(
            $sku,
            2,
            '123 Main St',
            'UPS',
            $locationId,
            $tenantId

        $this->assertInstanceOf(PurchaseShippingLabelResult::class, $result);
        $this->assertEquals('TRACK-123', $result->trackingNumber);
        $this->assertEquals('http://labels.com/123', $result->labelUrl);
        $this->assertEquals(500, $result->rateCents);
    }

    public function testExecuteWithoutCostLayerRepositoryWorks()
    {
        $useCase = new PurchaseShippingLabel(
            $this->outboxRepository





        $this->productRepository->expects($this->once())->method('save');
        $this->ledgerRepository->expects($this->once())->method('append');
        $this->shipmentRepository->expects($this->once())->method('save');
        $this->journalRepository->expects($this->once())->method('save');
        $this->outboxRepository->expects($this->once())->method('save');

        $result = $useCase->execute($sku, 2, '123 Main St', 'UPS', $locationId, 'TENANT-123');

    }

    /**
     * @dataProvider missingParametersProvider
     */
    public function testExecuteThrowsExceptionOnMissingParameters(string $sku, int $quantity, string $destination, string $carrier, string $location, string $tenant)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required parameters for shipping label purchase.");

        $this->useCase->execute($sku, $quantity, $destination, $carrier, $location, $tenant);
    }

    public function missingParametersProvider(): array
    {
        return [
            'empty sku' => ['', 1, '123 Main St', 'UPS', 'LOC-STOREFRONT', 'TENANT-123'],
            'whitespace sku' => ['   ', 1, '123 Main St', 'UPS', 'LOC-STOREFRONT', 'TENANT-123'],
            'zero quantity' => ['SKU123', 0, '123 Main St', 'UPS', 'LOC-STOREFRONT', 'TENANT-123'],
            'negative quantity' => ['SKU123', -5, '123 Main St', 'UPS', 'LOC-STOREFRONT', 'TENANT-123'],
            'empty destination' => ['SKU123', 1, '', 'UPS', 'LOC-STOREFRONT', 'TENANT-123'],
            'empty carrier' => ['SKU123', 1, '123 Main St', '', 'LOC-STOREFRONT', 'TENANT-123'],
            'empty location' => ['SKU123', 1, '123 Main St', 'UPS', '', 'TENANT-123'],
            'empty tenant' => ['SKU123', 1, '123 Main St', 'UPS', 'LOC-STOREFRONT', ''],
        ];
    }

    public function testExecuteHandlesZeroAsValidStringForParameters()
    {
        // SKU has a rule requiring length >= 3 and valid format, so we can't use '0'.
        // We will use a valid SKU, but '0' for the other parameters to check false positives in empty().
        $sku = 'SKU-0';
        $locationId = 'LOC-0';

        // This validates that our "0" parameters bypassed the initial validation
            ->willReturn(null); // Return null to easily catch the expected exception

        $this->expectExceptionMessage("Inventory item not found for SKU {$sku} at location {$locationId}.");

        $this->useCase->execute($sku, 1, '0', '0', $locationId, '0');
    }

    public function testExecuteThrowsExceptionWhenProductNotFound()
    {
            ->willReturn(null);

        $this->expectExceptionMessage("Inventory item not found for SKU NON-EXISTENT at location LOC-STOREFRONT.");

        $this->useCase->execute('NON-EXISTENT', 1, '123 Main St', 'UPS', 'LOC-STOREFRONT', 'TENANT-123');
    }

    public function testExecuteThrowsInsufficientStockExceptionWhenStockIsTooLow()
    {
        $requested = 5;
        $available = 2;

        $product = $this->createProductWithStock($sku, $locationId, $available);


        $this->expectException(InsufficientStockException::class);

        $this->useCase->execute($sku, $requested, '123 Main St', 'UPS', $locationId, 'TENANT-123');
    }

    public function testExecuteThrowsExceptionWhenCostLayersAreInsufficient()
    {



use InventoryApp\Application\Ports\LabelResult;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;

{
    private $shipmentRepo;
    private $productRepo;
    private $ledgerRepo;
    private $journalRepo;
    private $outboxRepo;
    private $costLayerRepo;

    {
        $this->shipmentRepo = $this->createMock(ShipmentRepositoryInterface::class);
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
            ->with($this->callback(fn($s) => $s->getValue() === $sku))

            ->with($product);

        // Setup Carrier Service
        $labelResult = new LabelResult('TRACKING-123', 'http://label.url', 1500);
            ->with($sku, $quantity, $destination, $carrier)

        // Expect Ledger Entry
        $this->ledgerRepo->expects($this->once())->method('append');

        // Setup Cost Layers
        $layer1 = new InventoryCostLayer('layer-1', $sku, $tenantId, 1, 1000, new DateTimeImmutable());
        $layer2 = new InventoryCostLayer('layer-2', $sku, $tenantId, 5, 1200, new DateTimeImmutable());

        $this->costLayerRepo->expects($this->once())
            ->with($sku, 'expiration_date ASC')
            ->willReturn([$layer1, $layer2]);

            ->method('saveBatch')
            ->with($this->callback(function ($layers) use ($layer1, $layer2) {
                return count($layers) === 2 && $layers[0] === $layer1 && $layers[1] === $layer2;

        // Expect Shipment, Journal, and Outbox saved
        $this->shipmentRepo->expects($this->once())->method('save');
        $this->journalRepo->expects($this->once())->method('save');
        $this->outboxRepo->expects($this->once())->method('save');

            $this->shipmentRepo,
            $this->productRepo,
            $this->ledgerRepo,
            $this->journalRepo,
            $this->outboxRepo,
            $this->costLayerRepo

        $result = $useCase->execute($sku, $quantity, $destination, $carrier, $locationId, $tenantId);

        $this->assertEquals('TRACKING-123', $result->trackingNumber);
        $this->assertEquals('http://label.url', $result->labelUrl);
        $this->assertEquals(1500, $result->rateCents);
        $this->assertEquals(3, $product->getStockAt(new LocationId($locationId))->getStockQuantity()->getValue());
    }

    public function testExecuteThrowsExceptionForMissingParameters(): void
    {
            $this->shipmentRepo, $this->carrierService, $this->productRepo,
            $this->ledgerRepo, $this->journalRepo, $this->outboxRepo, $this->costLayerRepo


        $useCase->execute('', 1, 'address', 'carrier', 'LOC-1', 'tenant-1');
    }

    public function testExecuteThrowsExceptionForNegativeQuantity(): void
    {


        $useCase->execute('SKU', -1, 'address', 'carrier', 'LOC-1', 'tenant-1');
    }

    public function testExecuteThrowsExceptionWhenProductNotFound(): void
    {


        $this->expectExceptionMessage("Inventory item not found for SKU TEST-SKU at location LOC-1.");

        $useCase->execute('TEST-SKU', 1, 'address', 'carrier', 'LOC-1', 'tenant-1');
    }

    public function testExecuteThrowsExceptionForInsufficientStock(): void
    {
        // 0 stock initially




        $useCase->execute($sku, 2, 'address', 'carrier', $locationId, 'tenant-1');
    }

    public function testExecuteThrowsExceptionForInsufficientCostLayers(): void
    {
        $quantity = 5;
        $product->getStockAt(new LocationId($locationId))->addStock(new Quantity(10), new Condition(Condition::NEW));


        $this->carrierService->expects($this->once())
            ->method('generateLabel')
            ->willReturn($labelResult);

        // Provide only 2 items in cost layers, but we're trying to consume 5
        $costLayer = new InventoryCostLayer('layer-1', $sku, 'TENANT-123', 2, 1000, new DateTimeImmutable());
        $this->costLayerRepository->expects($this->once())
            ->method('getActiveLayers')
            ->willReturn([$costLayer]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient cost layers to cover dispatch quantity of 5 for SKU {$sku}");

        $this->useCase->execute($sku, 5, '123 Main St', 'UPS', $locationId, 'TENANT-123');
        // Return only enough for 2, but need 5
        $layer1 = new InventoryCostLayer('layer-1', $sku, 'tenant-1', 2, 1000, new DateTimeImmutable());
        $this->costLayerRepo->expects($this->once())
            ->willReturn([$layer1]);

        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepo, $this->carrierService, $this->productRepo,
            $this->ledgerRepo, $this->journalRepo, $this->outboxRepo, $this->costLayerRepo
        );

        $this->expectExceptionMessage("Insufficient cost layers to cover dispatch quantity of 5 for SKU TEST-SKU");

        $useCase->execute($sku, $quantity, 'address', 'carrier', $locationId, 'tenant-1');
    }
}
