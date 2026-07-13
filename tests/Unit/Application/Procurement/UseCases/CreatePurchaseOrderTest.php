<?php

namespace Tests\Unit\Application\Procurement\UseCases;

use Exception;
use InventoryApp\Application\Procurement\UseCases\CreatePurchaseOrder;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreatePurchaseOrderTest extends TestCase
{
    public function testExecuteCreatesAndSavesPurchaseOrderSuccessfully(): void
    {
        $poRepository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $useCase = new CreatePurchaseOrder($poRepository);

        $data = [
            'purchaseOrderNumber' => 'PO-12345',
            'vendorId' => 'vendor-1',
            'tenantId' => 'tenant-1',
            'locationId' => 'location-1',
            'items' => [
                [
                    'variantId' => 'variant-1',
                    'quantity' => 10,
                    'unitCostCents' => 500,
                ],
                [
                    'variantId' => 'variant-2',
                    'quantity' => 5,
                    'unitCostCents' => 1000,
                ]
            ]
        ];

        $poRepository->expects($this->once())
            ->method('findByNumber')
            ->with('PO-12345')
            ->willReturn(null);

        $poRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PurchaseOrder $po) use ($data) {
                $this->assertEquals($data['purchaseOrderNumber'], $po->purchaseOrderNumber);
                $this->assertEquals($data['vendorId'], $po->vendorId);
                $this->assertEquals($data['tenantId'], $po->tenantId);
                $this->assertEquals($data['locationId'], $po->locationId);
                $this->assertEquals(PurchaseOrderStatus::Draft, $po->getStatus());

                $items = $po->getItems();
                $this->assertCount(2, $items);

                $this->assertEquals($data['items'][0]['variantId'], $items[0]->variantId);
                $this->assertEquals($data['items'][0]['quantity'], $items[0]->quantity);
                $this->assertEquals($data['items'][0]['unitCostCents'], $items[0]->unitCostCents);
                $this->assertEquals(0, $items[0]->getReceivedQuantity());

                $this->assertEquals($data['items'][1]['variantId'], $items[1]->variantId);
                $this->assertEquals($data['items'][1]['quantity'], $items[1]->quantity);
                $this->assertEquals($data['items'][1]['unitCostCents'], $items[1]->unitCostCents);
                $this->assertEquals(0, $items[1]->getReceivedQuantity());

                return true;
            }));

        $resultPo = $useCase->execute($data);

        $this->assertInstanceOf(PurchaseOrder::class, $resultPo);
        $this->assertEquals($data['purchaseOrderNumber'], $resultPo->purchaseOrderNumber);
        $this->assertEquals(PurchaseOrderStatus::Draft, $resultPo->getStatus());
    }

    public function testExecuteThrowsExceptionWhenPurchaseOrderAlreadyExists(): void
    {
        $poRepository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $useCase = new CreatePurchaseOrder($poRepository);

        $data = [
            'purchaseOrderNumber' => 'PO-12345',
            'vendorId' => 'vendor-1',
            'tenantId' => 'tenant-1',
            'locationId' => 'location-1',
            'items' => [
                [
                    'variantId' => 'variant-1',
                    'quantity' => 10,
                    'unitCostCents' => 500,
                ]
            ]
        ];

        $existingPo = new PurchaseOrder(
            'existing-id',
            'PO-12345',
            'vendor-1',
            'tenant-1',
            'location-1'
        );

        $poRepository->expects($this->once())
            ->method('findByNumber')
            ->with('PO-12345')
            ->willReturn($existingPo);

        $poRepository->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Purchase order with number PO-12345 already exists.");

        $useCase->execute($data);
    }
}
