<?php

namespace Tests\Unit\Application\Procurement\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Procurement\UseCases\CreatePurchaseOrder;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use Exception;

class CreatePurchaseOrderTest extends TestCase
{
    public function testExecuteCreatesAndSavesNewPurchaseOrder(): void
    {
        $repositoryMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('findByNumber')->willReturn(null);
        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PurchaseOrder $po) {
                return $po->purchaseOrderNumber === 'PO-1234'
                    && $po->vendorId === 'VENDOR-1'
                    && $po->tenantId === 'TENANT-1'
                    && $po->locationId === 'LOC-1'
                    && $po->getStatus() === PurchaseOrderStatus::Draft
                    && count($po->getItems()) === 2
                    && $po->getItems()[0]->variantId === 'VAR-1'
                    && $po->getItems()[0]->quantity === 10
                    && $po->getItems()[0]->unitCostCents === 1500
                    && $po->getItems()[1]->variantId === 'VAR-2'
                    && $po->getItems()[1]->quantity === 5
                    && $po->getItems()[1]->unitCostCents === 2500;
            }));

        $useCase = new CreatePurchaseOrder($repositoryMock);

        $data = [
            'purchaseOrderNumber' => 'PO-1234',
            'vendorId' => 'VENDOR-1',
            'tenantId' => 'TENANT-1',
            'locationId' => 'LOC-1',
            'items' => [
                [
                    'variantId' => 'VAR-1',
                    'quantity' => 10,
                    'unitCostCents' => 1500,
                ],
                [
                    'variantId' => 'VAR-2',
                    'quantity' => 5,
                    'unitCostCents' => 2500,
                ],
            ],
        ];

        $po = $useCase->execute($data);

        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertEquals('PO-1234', $po->purchaseOrderNumber);
        $this->assertEquals('VENDOR-1', $po->vendorId);
    }

    public function testExecuteThrowsWhenPurchaseOrderAlreadyExists(): void
    {
        $existingPo = new PurchaseOrder(
            'uuid',
            'PO-1234',
            'VENDOR-1',
            'TENANT-1',
            'LOC-1',
            PurchaseOrderStatus::Draft,
            []
        );

        $repositoryMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('findByNumber')->willReturn($existingPo);
        $repositoryMock->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Purchase order with number PO-1234 already exists.');

        $useCase = new CreatePurchaseOrder($repositoryMock);

        $data = [
            'purchaseOrderNumber' => 'PO-1234',
            'vendorId' => 'VENDOR-1',
            'tenantId' => 'TENANT-1',
            'locationId' => 'LOC-1',
            'items' => [],
        ];

        $useCase->execute($data);
    }
}
