<?php

declare(strict_types=1);

namespace Tests\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;
use InventoryApp\Application\Ports\LabelResult;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Exception;

/** @group unit */
final class PurchaseShippingLabelTest extends TestCase
{
    private ShipmentRepositoryInterface $shipmentRepository;
    private CarrierServiceInterface $carrierService;
    private ProductRepositoryInterface $productRepository;
    private LedgerRepositoryInterface $ledgerRepository;
    private JournalRepositoryInterface $journalRepository;
    private OutboxRepositoryInterface $outboxRepository;
    private CostLayerRepositoryInterface $costLayerRepository;

    private PurchaseShippingLabel $useCase;

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

    public function testThrowsExceptionOnMissingParameters(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required parameters for shipping label purchase.");

        $this->useCase->execute(
            '',
            10,
            '123 Main St',
            'UPS',
            'LOC-1',
            'TENANT-1'
        );
    }

    public function testThrowsExceptionOnInvalidQuantity(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required parameters for shipping label purchase.");

        $this->useCase->execute(
            'SKU-1',
            0,
            '123 Main St',
            'UPS',
            'LOC-1',
            'TENANT-1'
        );
    }

    public function testThrowsExceptionWhenProductNotFound(): void
    {
        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn(null);

        $this->productRepository->expects($this->never())->method('save');
        $this->ledgerRepository->expects($this->never())->method('append');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Inventory item not found for SKU SKU-1 at location LOC-1.");

        $this->useCase->execute(
            'SKU-1',
            10,
            '123 Main St',
            'UPS',
            'LOC-1',
            'TENANT-1'
        );
    }

    public function testThrowsExceptionOnInsufficientStock(): void
    {
        $product = Product::create(
            Uuid::uuid4()->toString(),
            new SKU('SKU-1'),
            'Test Product',
            new Department('DEP-1'),
            new LocationId('LOC-1'),
            new Quantity(5)
        );

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $this->productRepository->expects($this->never())->method('save');
        $this->ledgerRepository->expects($this->never())->method('append');

        $this->expectException(InsufficientStockException::class);

        $this->useCase->execute(
            'SKU-1',
            10, // requesting 10, but only 5 available
            '123 Main St',
            'UPS',
            'LOC-1',
            'TENANT-1'
        );
    }

    public function testSuccessfulExecution(): void
    {
        $product = Product::create(
            Uuid::uuid4()->toString(),
            new SKU('SKU-1'),
            'Test Product',
            new Department('DEP-1'),
            new LocationId('LOC-1'),
            new Quantity(20)
        );

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $labelResult = new LabelResult('TRACK-123', 'http://label.url', 1500);
        $this->carrierService->expects($this->once())
            ->method('generateLabel')
            ->willReturn($labelResult);

        $layer = new InventoryCostLayer(Uuid::uuid4()->toString(), 'SKU-1', 'TENANT-1', 50, 1000, new DateTimeImmutable());
        $this->costLayerRepository->expects($this->once())
            ->method('getActiveLayers')
            ->willReturn([$layer]);

        $this->productRepository->expects($this->once())->method('save');
        $this->ledgerRepository->expects($this->once())->method('append');
        $this->costLayerRepository->expects($this->once())->method('saveBatch');
        $this->shipmentRepository->expects($this->once())->method('save');
        $this->journalRepository->expects($this->once())->method('save');
        $this->outboxRepository->expects($this->once())->method('save');

        $result = $this->useCase->execute(
            'SKU-1',
            10,
            '123 Main St',
            'UPS',
            'LOC-1',
            'TENANT-1'
        );

        $this->assertEquals('TRACK-123', $result->trackingNumber);
        $this->assertEquals('http://label.url', $result->labelUrl);
        $this->assertEquals(1500, $result->rateCents);
    }

    public function testThrowsExceptionOnInsufficientCostLayers(): void
    {
        $product = Product::create(
            Uuid::uuid4()->toString(),
            new SKU('SKU-1'),
            'Test Product',
            new Department('DEP-1'),
            new LocationId('LOC-1'),
            new Quantity(20)
        );

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $labelResult = new LabelResult('TRACK-123', 'http://label.url', 1500);
        $this->carrierService->expects($this->once())
            ->method('generateLabel')
            ->willReturn($labelResult);

        // Only 5 quantity available in cost layers, need 10
        $layer = new InventoryCostLayer(Uuid::uuid4()->toString(), 'SKU-1', 'TENANT-1', 5, 1000, new DateTimeImmutable());
        $this->costLayerRepository->expects($this->once())
            ->method('getActiveLayers')
            ->willReturn([$layer]);

        $this->costLayerRepository->expects($this->never())->method('saveBatch');
        $this->shipmentRepository->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient cost layers to cover dispatch quantity of 10 for SKU SKU-1");

        $this->useCase->execute(
            'SKU-1',
            10,
            '123 Main St',
            'UPS',
            'LOC-1',
            'TENANT-1'
        );
    }
}
