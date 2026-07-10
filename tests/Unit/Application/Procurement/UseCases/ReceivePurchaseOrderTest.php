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
    public function testExecuteSuccessfullyReceivesStock()
    {
        $poRepository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $costLayerRepository = $this->createMock(CostLayerRepositoryInterface::class);
        $events = $this->createMock(EventDispatcherInterface::class);

        $poItem = new PurchaseOrderItem('item-1', 'VAR-1', 10, 1000, 0);
        $po = new PurchaseOrder(
            'po-1',
            'PO-1234',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Sent,
            [$poItem]
        );

        $poRepository->expects($this->once())
            ->method('findById')
            ->with('po-1')
            ->willReturn($po);

        $product = new Product('prod-1', new SKU('VAR-1'), 'Test Prod', new Department('DEP-1'));

        $productRepository->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'VAR-1';
            }))
            ->willReturn($product);

        $costLayerRepository->expects($this->once())
            ->method('saveBatch')
            ->with($this->callback(function (array $layers) {
                return count($layers) === 1 &&
                       $layers[0]->variantId === 'VAR-1' &&
                       $layers[0]->originalQuantity === 5 &&
                       $layers[0]->unitCostCents === 1000;
            }));

        $poRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PurchaseOrder $savedPo) {
                $items = $savedPo->getItems();
                return $savedPo->getStatus() === PurchaseOrderStatus::PartiallyReceived &&
                       $items[0]->getReceivedQuantity() === 5;
            }));

        $useCase = new ReceivePurchaseOrder($poRepository, $productRepository, $costLayerRepository, $events);

        $useCase->execute([
            'purchaseOrderId' => 'po-1',
            'items' => [
                [
                    'variantId' => 'VAR-1',
                    'quantityReceived' => 5
                ]
            ]
        ]);
    }

    public function testExecuteThrowsExceptionWhenPurchaseOrderNotFound()
    {
        $poRepository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $costLayerRepository = $this->createMock(CostLayerRepositoryInterface::class);
        $events = $this->createMock(EventDispatcherInterface::class);

        $poRepository->expects($this->once())
            ->method('findById')
            ->with('po-invalid')
            ->willReturn(null);

        $poRepository->expects($this->never())->method('save');
        $costLayerRepository->expects($this->never())->method('saveBatch');

        $useCase = new ReceivePurchaseOrder($poRepository, $productRepository, $costLayerRepository, $events);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Purchase order with ID po-invalid not found.");

        $useCase->execute([
            'purchaseOrderId' => 'po-invalid',
            'items' => []
        ]);
    }

    public function testExecuteThrowsExceptionWhenItemNotInPurchaseOrder()
    {
        $poRepository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $costLayerRepository = $this->createMock(CostLayerRepositoryInterface::class);
        $events = $this->createMock(EventDispatcherInterface::class);

        $poItem = new PurchaseOrderItem('item-1', 'VAR-1', 10, 1000, 0);
        $po = new PurchaseOrder(
            'po-1',
            'PO-1234',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Sent,
            [$poItem]
        );

        $poRepository->expects($this->once())
            ->method('findById')
            ->with('po-1')
            ->willReturn($po);

        $poRepository->expects($this->never())->method('save');
        $costLayerRepository->expects($this->never())->method('saveBatch');

        $useCase = new ReceivePurchaseOrder($poRepository, $productRepository, $costLayerRepository, $events);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Item VAR-UNKNOWN not found in purchase order PO-1234.");

        $useCase->execute([
            'purchaseOrderId' => 'po-1',
            'items' => [
                [
                    'variantId' => 'VAR-UNKNOWN',
                    'quantityReceived' => 5
                ]
            ]
        ]);
    }
}
