<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;

use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;
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
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Shipping\Events\ShipmentCreatedEvent;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;

class PurchaseShippingLabelTest extends TestCase
{
    public function test_it_successfully_purchases_shipping_label_and_records_all_domain_events(): void
    {
        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $carrierService = $this->createMock(CarrierServiceInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $ledgerRepository = $this->createMock(LedgerRepositoryInterface::class);
        $journalRepository = $this->createMock(JournalRepositoryInterface::class);
        $outboxRepository = $this->createMock(OutboxRepositoryInterface::class);

        $skuStr = 'TEST-SKU';
        $sku = new SKU($skuStr);
        $locationIdStr = 'LOC-WAREHOUSE-1';
        $locationId = new LocationId($locationIdStr);
        $quantityInt = 5;
        $quantity = new Quantity($quantityInt);
        $destinationAddress = '123 Test St, Test City, TS 12345';
        $carrier = 'UPS';
        $tenantId = 'TENANT-123';

        $product = Product::create(
            'prod-123',
            $sku,
            'Test Product',
            new Department('TEST'),
            $locationId,
            new Quantity(10)
        );

        $productRepository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn($product);

            ->method('save')
            ->with($this->callback(function (Product $p) use ($quantityInt) {
                return $p->getTotalStockQuantity()->getValue() === (10 - $quantityInt);
            }));

        $labelResult = new LabelResult('TRACK-987654321', 'https://example.com/label.png', 1500);
        $carrierService->expects($this->once())
            ->method('generateLabel')
            ->with($skuStr, $quantityInt, $destinationAddress, $carrier)
            ->willReturn($labelResult);

        $ledgerRepository->expects($this->once())
            ->method('append')
            ->with($this->callback(function ($entry) use ($skuStr, $quantityInt, $locationIdStr) {
                return $entry->variantId === $skuStr
                    && $entry->quantity === -$quantityInt
                    && $entry->metadata['locationId'] === $locationIdStr;

        $shipmentRepository->expects($this->once())
            ->with($this->callback(function (Shipment $shipment) use ($skuStr, $quantityInt, $destinationAddress, $carrier, $labelResult) {
                return $shipment->sku === $skuStr
                    && $shipment->quantity === $quantityInt
                    && $shipment->destinationAddress === $destinationAddress
                    && $shipment->carrier === $carrier
                    && $shipment->trackingNumber === $labelResult->trackingNumber
                    && $shipment->labelUrl === $labelResult->labelUrl
                    && $shipment->shippingRateCents === $labelResult->rateCents;

        $journalRepository->expects($this->once())
            ->with($this->callback(function (JournalEntry $entry) use ($tenantId, $labelResult) {
                return $entry->tenantId === $tenantId
                    && str_contains($entry->description, $labelResult->trackingNumber)
                    && count($entry->lines()) === 2;

        $outboxRepository->expects($this->once())
            ->with($this->callback(function (ShipmentCreatedEvent $event) use ($skuStr, $quantityInt, $carrier, $labelResult) {
                return $event->sku === $skuStr
                    && $event->quantity === $quantityInt
                    && $event->carrier === $carrier
                    && $event->trackingNumber === $labelResult->trackingNumber
                    && $event->rateCents === $labelResult->rateCents;

        $useCase = new PurchaseShippingLabel(
            $shipmentRepository,
            $carrierService,
            $productRepository,
            $ledgerRepository,
            $journalRepository,
            $outboxRepository

        $result = $useCase->execute(
            $skuStr,
            $quantityInt,
            $destinationAddress,
            $carrier,
            $locationIdStr,
            $tenantId

        $this->assertInstanceOf(PurchaseShippingLabelResult::class, $result);
        $this->assertEquals($labelResult->trackingNumber, $result->trackingNumber);
        $this->assertEquals($labelResult->labelUrl, $result->labelUrl);
        $this->assertEquals($labelResult->rateCents, $result->rateCents);
    }

    public function invalidParametersProvider(): array
    {
        return [
            'empty sku' => ['', 5, '123 St', 'UPS', 'LOC-1', 'TENANT-1'],
            'zero quantity' => ['SKU', 0, '123 St', 'UPS', 'LOC-1', 'TENANT-1'],
            'negative quantity' => ['SKU', -5, '123 St', 'UPS', 'LOC-1', 'TENANT-1'],
            'empty destination' => ['SKU', 5, '', 'UPS', 'LOC-1', 'TENANT-1'],
            'empty carrier' => ['SKU', 5, '123 St', '', 'LOC-1', 'TENANT-1'],
            'empty location' => ['SKU', 5, '123 St', 'UPS', '', 'TENANT-1'],
            'empty tenant' => ['SKU', 5, '123 St', 'UPS', 'LOC-1', ''],
        ];
    }

    /**
     * @dataProvider invalidParametersProvider
     */
    public function test_it_throws_exception_if_required_parameters_are_missing(
        string $sku,
        int $quantity,
        string $destinationAddress,
        string $carrier,
        string $locationId,
        string $tenantId
    ): void {
            $this->createMock(ShipmentRepositoryInterface::class),
            $this->createMock(CarrierServiceInterface::class),
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(LedgerRepositoryInterface::class),
            $this->createMock(JournalRepositoryInterface::class),
            $this->createMock(OutboxRepositoryInterface::class)

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required parameters for shipping label purchase.');

        $useCase->execute($sku, $quantity, $destinationAddress, $carrier, $locationId, $tenantId);
    }

    public function test_it_throws_exception_if_product_not_found(): void
    {
        $skuStr = 'UNKNOWN-SKU';
        $locationId = 'LOC-1';

            ->with(new SKU($skuStr))
            ->willReturn(null);


        $this->expectExceptionMessage("Inventory item not found for SKU {$skuStr} at location {$locationId}.");

        $useCase->execute($skuStr, 5, '123 St', 'UPS', $locationId, 'TENANT-1');
    }

    public function test_it_throws_insufficient_stock_exception_if_quantity_exceeds_available(): void
    {
        $locationIdStr = 'LOC-1';

            new Quantity(3)


use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use DateTimeImmutable;
use Exception;

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
        $this->carrierService->expects($this->once())
            ->with($sku, $quantity, $destination, $carrier)

        // Expect Ledger Entry
        $this->ledgerRepo->expects($this->once())->method('append');

        // Setup Cost Layers
        $layer1 = new InventoryCostLayer('layer-1', $sku, $tenantId, 1, 1000, new DateTimeImmutable());
        $layer2 = new InventoryCostLayer('layer-2', $sku, $tenantId, 5, 1200, new DateTimeImmutable());

        $this->costLayerRepo->expects($this->once())
            ->method('getActiveLayers')
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
            $this->carrierService,
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

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required parameters for shipping label purchase.");

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


        );

        $this->expectException(InsufficientStockException::class);

        $useCase->execute($skuStr, 5, '123 St', 'UPS', $locationIdStr, 'TENANT-1');
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
