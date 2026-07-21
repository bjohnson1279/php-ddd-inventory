<?php

namespace Tests\Unit\Application\Procurement\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Procurement\UseCases\ReceivePurchaseOrder;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Exception;

class ReceivePurchaseOrderTest extends TestCase
{
    private $poRepository;
    private $productRepository;
    private $costLayerRepository;
    private $eventDispatcher;
    private $useCase;

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

    public function testExecuteThrowsExceptionIfPurchaseOrderNotFound(): void
    {
        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Purchase order with ID po-123 not found.");

        $this->useCase->execute(['purchaseOrderId' => 'po-123', 'items' => []]);
    }

    public function testExecuteThrowsExceptionIfItemNotFoundInPurchaseOrder(): void
    {
        $po = new PurchaseOrder(
            'po-123',
            'PO-NUM-001',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Sent,
            [] // Empty items
        );

        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Item unknown-variant not found in purchase order PO-NUM-001.");

        $this->useCase->execute([
            'purchaseOrderId' => 'po-123',
            'items' => [
                ['variantId' => 'unknown-variant', 'quantityReceived' => 5]
            ]
        ]);
    }

    public function testExecuteSuccessfullyReceivesPurchaseOrder(): void
    {
        $item1 = new PurchaseOrderItem('item-1', 'VARIANT-1', 10, 500); // quantity 10, 500 cents
        $po = new PurchaseOrder(
            'po-123',
            'PO-NUM-001',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Sent,
            [$item1]
        );

        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        $productMock = Product::create(
            'prod-1',
            new SKU('VARIANT-1'),
            'Product 1',
            new Department('DEP1'),
            new LocationId('LOC-1'),
            new Quantity(0)
        );

        // ReceiveStock dependency
        $this->productRepository->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'VARIANT-1';
            }))
            ->willReturn($productMock);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getTotalStockQuantity()->getValue() === 5;
            }));

        $this->costLayerRepository->expects($this->once())
            ->method('saveBatch')
            ->with($this->callback(function (array $costLayers) {
                if (count($costLayers) !== 1) {
                    return false;
                }
                $layer = $costLayers[0];
                return $layer->variantId === 'VARIANT-1'
                    && $layer->tenantId === 'tenant-1'
                    && $layer->originalQuantity === 5
                    && $layer->unitCostCents === 500
                    && $layer->purchaseOrderId === 'po-123';
            }));

        $this->poRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PurchaseOrder $po) {
                return $po->getStatus() === PurchaseOrderStatus::PartiallyReceived;
            }));

        $this->useCase->execute([
            'purchaseOrderId' => 'po-123',
            'items' => [
                ['variantId' => 'VARIANT-1', 'quantityReceived' => 5]
            ]
        ]);
    }
}
