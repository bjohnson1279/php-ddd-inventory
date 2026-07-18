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
        );
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

        $this->productRepository->expects($this->once())
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

        $this->costLayerRepository->expects($this->once())
            ->method('saveBatch');

        $this->shipmentRepository->expects($this->once())
            ->method('save');

        $this->journalRepository->expects($this->once())
            ->method('save');

        $this->outboxRepository->expects($this->once())
            ->method('save');

        $result = $this->useCase->execute(
            $sku,
            2,
            '123 Main St',
            'UPS',
            $locationId,
            $tenantId
        );

        $this->assertInstanceOf(PurchaseShippingLabelResult::class, $result);
        $this->assertEquals('TRACK-123', $result->trackingNumber);
        $this->assertEquals('http://labels.com/123', $result->labelUrl);
        $this->assertEquals(500, $result->rateCents);
    }

    public function testExecuteWithoutCostLayerRepositoryWorks()
    {
        $useCase = new PurchaseShippingLabel(
            $this->shipmentRepository,
            $this->carrierService,
            $this->productRepository,
            $this->ledgerRepository,
            $this->journalRepository,
            $this->outboxRepository
        );

        $sku = 'TSHIRT-L-RED';
        $locationId = 'LOC-STOREFRONT';

        $product = $this->createProductWithStock($sku, $locationId, 10);

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $labelResult = new LabelResult('TRACK-123', 'http://labels.com/123', 500);
        $this->carrierService->expects($this->once())
            ->method('generateLabel')
            ->willReturn($labelResult);

        $this->productRepository->expects($this->once())->method('save');
        $this->ledgerRepository->expects($this->once())->method('append');
        $this->shipmentRepository->expects($this->once())->method('save');
        $this->journalRepository->expects($this->once())->method('save');
        $this->outboxRepository->expects($this->once())->method('save');

        $result = $useCase->execute($sku, 2, '123 Main St', 'UPS', $locationId, 'TENANT-123');

        $this->assertInstanceOf(PurchaseShippingLabelResult::class, $result);
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
        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->with($this->equalTo(new SKU($sku)))
            ->willReturn(null); // Return null to easily catch the expected exception

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Inventory item not found for SKU {$sku} at location {$locationId}.");

        $this->useCase->execute($sku, 1, '0', '0', $locationId, '0');
    }

    public function testExecuteThrowsExceptionWhenProductNotFound()
    {
        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Inventory item not found for SKU NON-EXISTENT at location LOC-STOREFRONT.");

        $this->useCase->execute('NON-EXISTENT', 1, '123 Main St', 'UPS', 'LOC-STOREFRONT', 'TENANT-123');
    }

    public function testExecuteThrowsInsufficientStockExceptionWhenStockIsTooLow()
    {
        $sku = 'TSHIRT-L-RED';
        $locationId = 'LOC-STOREFRONT';
        $requested = 5;
        $available = 2;

        $product = $this->createProductWithStock($sku, $locationId, $available);

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $this->expectException(InsufficientStockException::class);

        $this->useCase->execute($sku, $requested, '123 Main St', 'UPS', $locationId, 'TENANT-123');
    }

    public function testExecuteThrowsExceptionWhenCostLayersAreInsufficient()
    {
        $sku = 'TSHIRT-L-RED';
        $locationId = 'LOC-STOREFRONT';

        $product = $this->createProductWithStock($sku, $locationId, 10);

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $labelResult = new LabelResult('TRACK-123', 'http://labels.com/123', 500);
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
    }
}
