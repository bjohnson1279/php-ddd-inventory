<?php

namespace Tests\Unit\Application\Procurement\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Procurement\UseCases\ReceivePurchaseOrder;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class ReceivePurchaseOrderTest extends TestCase
{
    private $poRepository;
    private $productRepository;
    private $costLayerRepository;
    private $eventDispatcher;
    private ReceivePurchaseOrder $useCase;

    protected function setUp(): void
    {
        $this->poRepository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->costLayerRepository = $this->createMock(CostLayerRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->useCase = new ReceivePurchaseOrder(
            $this->poRepository,
            $this->productRepository,
            $this->costLayerRepository,
            $this->eventDispatcher
        );
    }

    public function testExecuteThrowsExceptionWhenPurchaseOrderNotFound(): void
    {
        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Purchase order with ID po-123 not found.");

        $this->useCase->execute([
            'purchaseOrderId' => 'po-123',
            'items' => []
        ]);
    }

    public function testExecuteThrowsExceptionWhenItemNotFoundInPurchaseOrder(): void
    {
        $po = new PurchaseOrder(
            id: 'po-123',
            purchaseOrderNumber: 'PO-001',
            vendorId: 'vendor-1',
            tenantId: 'tenant-1',
            locationId: 'LOC-MAIN',
            status: PurchaseOrderStatus::Sent,
            items: [
                new PurchaseOrderItem('item-1', 'variant-1', 10, 1000)
            ]
        );

        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Item variant-2 not found in purchase order PO-001.");

        $this->useCase->execute([
            'purchaseOrderId' => 'po-123',
            'items' => [
                ['variantId' => 'variant-2', 'quantityReceived' => 5]
            ]
        ]);
    }

    public function testExecuteReceivesStockAndUpdatesPurchaseOrderAndCostLayers(): void
    {
        $poItem = new PurchaseOrderItem('item-1', 'variant-1', 10, 1500);
        $po = new PurchaseOrder(
            id: 'po-123',
            purchaseOrderNumber: 'PO-001',
            vendorId: 'vendor-1',
            tenantId: 'tenant-1',
            locationId: 'LOC-MAIN',
            status: PurchaseOrderStatus::Sent,
            items: [$poItem]
        );

        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        // Product Mock setup for ReceiveStock
        $product = Product::create(
            'prod-1',
            new SKU('variant-1'),
            'Test Product',
            new Department('GENERAL'),
            new LocationId('LOC-MAIN'),
            new Quantity(50)
        );

        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'VARIANT-1';
            }))
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getTotalStockQuantity()->getValue() === 55;
            }));

        $this->costLayerRepository->expects($this->once())
            ->method('saveBatch')
            ->with($this->callback(function (array $layers) {
                if (count($layers) !== 1) return false;
                $layer = $layers[0];
                return $layer->variantId === 'variant-1'
                    && $layer->tenantId === 'tenant-1'
                    && $layer->originalQuantity === 5
                    && $layer->unitCostCents === 1500
                    && $layer->purchaseOrderId === 'po-123';
            }));

        $this->poRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PurchaseOrder $savedPo) use ($po) {
                return $savedPo === $po && $po->getStatus() === PurchaseOrderStatus::PartiallyReceived;
            }));

        $this->useCase->execute([
            'purchaseOrderId' => 'po-123',
            'items' => [
                ['variantId' => 'variant-1', 'quantityReceived' => 5]
            ]
        ]);

        $this->assertEquals(5, $poItem->getReceivedQuantity());
    }
}
