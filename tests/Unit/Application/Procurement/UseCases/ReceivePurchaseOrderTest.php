<?php

namespace Tests\Unit\Application\Procurement\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Procurement\UseCases\ReceivePurchaseOrder;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Application\Inventory\Factories\ReceiveStockFactoryInterface;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use Exception;
use DomainException;

class ReceivePurchaseOrderTest extends TestCase
{
    private $poRepository;
    private $costLayerRepository;
    private $receiveStockFactory;
    private $receiveStock;
    private $useCase;

    protected function setUp(): void
    {
        $this->poRepository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->costLayerRepository = $this->createMock(CostLayerRepositoryInterface::class);
        $this->receiveStockFactory = $this->createMock(ReceiveStockFactoryInterface::class);
        $this->receiveStock = $this->createMock(ReceiveStock::class);

        $this->useCase = new ReceivePurchaseOrder(
            $this->poRepository,
            $this->costLayerRepository,
            $this->receiveStockFactory
        );
    }

    public function testExecuteSuccessfullyReceivesItems(): void
    {
        $poId = 'po-1';
        $variantId = 'VAR-1';
        $locationId = 'LOC-1';
        $tenantId = 'tenant-1';

        $item = new PurchaseOrderItem('item-1', $variantId, 10, 1500, 0);
        $po = new PurchaseOrder(
            $poId,
            'PO-100',
            'vendor-1',
            $tenantId,
            $locationId,
            PurchaseOrderStatus::Sent,
            [$item]
        );

        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn($po);

        $this->receiveStockFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->receiveStock);

        $this->receiveStock->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function ($sku, $locId, $qty, $ref) use ($variantId, $locationId) {
                $this->assertEquals($variantId, $sku->getValue());
                $this->assertEquals($locationId, $locId->getValue());
                $this->assertEquals(5, $qty->getValue());
                $this->assertEquals('PO-100', $ref);
            });

        $this->costLayerRepository->expects($this->once())
            ->method('saveBatch')
            ->willReturnCallback(function (array $layers) use ($variantId, $tenantId, $poId) {
                $this->assertCount(1, $layers);
                $layer = $layers[0];
                $this->assertEquals($variantId, $layer->variantId);
                $this->assertEquals($tenantId, $layer->tenantId);
                $this->assertEquals(5, $layer->originalQuantity);
                $this->assertEquals(1500, $layer->unitCostCents);
                $this->assertEquals($poId, $layer->purchaseOrderId);
            });

        $this->poRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PurchaseOrder $savedPo) use ($po) {
                return $savedPo === $po &&
                       $savedPo->getStatus() === PurchaseOrderStatus::PartiallyReceived &&
                       $savedPo->getItems()[0]->getReceivedQuantity() === 5;
            }));

        $this->useCase->execute([
            'purchaseOrderId' => $poId,
            'items' => [
                [
                    'variantId' => $variantId,
                    'quantityReceived' => 5
                ]
            ]
        ]);
    }

    public function testExecuteThrowsExceptionIfPurchaseOrderNotFound(): void
    {
        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with('non-existent-po')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Purchase order with ID non-existent-po not found.");

        $this->useCase->execute([
            'purchaseOrderId' => 'non-existent-po',
            'items' => []
        ]);
    }

    public function testExecuteThrowsExceptionIfItemNotFoundInPurchaseOrder(): void
    {
        $poId = 'po-1';
        $po = new PurchaseOrder(
            $poId,
            'PO-100',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Sent,
            [new PurchaseOrderItem('item-1', 'VAR-1', 10, 1500, 0)]
        );

        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn($po);

        $this->receiveStockFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->receiveStock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Item VAR-2 not found in purchase order PO-100.");

        $this->useCase->execute([
            'purchaseOrderId' => $poId,
            'items' => [
                [
                    'variantId' => 'VAR-2',
                    'quantityReceived' => 5
                ]
            ]
        ]);
    }

    public function testExecuteThrowsExceptionForInvalidPurchaseOrderStatus(): void
    {
        $poId = 'po-1';
        $variantId = 'VAR-1';

        $item = new PurchaseOrderItem('item-1', $variantId, 10, 1500, 0);
        // Created in Draft state, which should not allow receiving items
        $po = new PurchaseOrder(
            $poId,
            'PO-100',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Draft,
            [$item]
        );

        $this->poRepository->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn($po);

        $this->receiveStockFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->receiveStock);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Can only receive items on Sent or Partially Received purchase orders.");

        $this->useCase->execute([
            'purchaseOrderId' => $poId,
            'items' => [
                [
                    'variantId' => $variantId,
                    'quantityReceived' => 5
                ]
            ]
        ]);
    }
}
