<?php

namespace Tests\Unit\Application\Returns\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Returns\UseCases\CreateRMA;
use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use Exception;

class CreateRMATest extends TestCase
{
    public function testExecuteThrowsExceptionIfRMAAlreadyExists()
    {
        $repositoryMock = $this->createMock(RMARepositoryInterface::class);
        $rmaMock = $this->createMock(RMA::class);

        $dto = [
            'rmaNumber' => 'RMA-12345',
            'tenantId' => 'tenant-1',
            'customerId' => 'cust-1',
            'locationId' => 'LOC-1',
            'items' => [
                [
                    'variantId' => 'var-1',
                    'quantity' => 2,
                    'unitCostCents' => 1000,
                ],
            ],
        ];

        $repositoryMock->expects($this->once())
            ->method('findByNumber')
            ->with('RMA-12345')
            ->willReturn($rmaMock);

        $repositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("RMA with number RMA-12345 already exists.");

        $useCase = new CreateRMA($repositoryMock);
        $useCase->execute($dto);
    }

    public function testExecuteCreatesAndSavesRMAWithEmptyItems()
    {
        $repositoryMock = $this->createMock(RMARepositoryInterface::class);

        $dto = [
            'rmaNumber' => 'RMA-EMPTY',
            'tenantId' => 'tenant-1',
            'customerId' => 'cust-1',
            'locationId' => 'LOC-1',
            'items' => [],
        ];

        $repositoryMock->expects($this->once())
            ->method('findByNumber')
            ->with('RMA-EMPTY')
            ->willReturn(null);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RMA $rma) use ($dto) {
                return $rma->getRmaNumber() === $dto['rmaNumber'] &&
                       $rma->getStatus() === RMAStatus::Requested &&
                       count($rma->getItems()) === 0;
            }));

        $useCase = new CreateRMA($repositoryMock);
        $rma = $useCase->execute($dto);

        $this->assertInstanceOf(RMA::class, $rma);
        $this->assertCount(0, $rma->getItems());
    }

    public function testExecuteCreatesAndSavesRMAWithMultipleItems()
    {
        $repositoryMock = $this->createMock(RMARepositoryInterface::class);

        $dto = [
            'rmaNumber' => 'RMA-MULTI',
            'tenantId' => 'tenant-1',
            'customerId' => 'cust-1',
            'locationId' => 'LOC-1',
            'items' => [
                [
                    'variantId' => 'var-1',
                    'quantity' => 2,
                    'unitCostCents' => 1000,
                ],
                [
                    'variantId' => 'var-2',
                    'quantity' => 5,
                    'unitCostCents' => 500,
                ],
            ],
        ];

        $repositoryMock->expects($this->once())
            ->method('findByNumber')
            ->with('RMA-MULTI')
            ->willReturn(null);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RMA $rma) use ($dto) {
                $items = $rma->getItems();
                return $rma->getRmaNumber() === $dto['rmaNumber'] &&
                       count($items) === 2 &&
                       $items[0]->getVariantId() === 'var-1' &&
                       $items[0]->getQuantity() === 2 &&
                       $items[1]->getVariantId() === 'var-2' &&
                       $items[1]->getQuantity() === 5;
            }));

        $useCase = new CreateRMA($repositoryMock);
        $rma = $useCase->execute($dto);

        $this->assertInstanceOf(RMA::class, $rma);
        $this->assertCount(2, $rma->getItems());
        $this->assertEquals('var-1', $rma->getItems()[0]->getVariantId());
        $this->assertEquals('var-2', $rma->getItems()[1]->getVariantId());
    }

    public function testExecuteCreatesAndSavesRMA()
    {
        $repositoryMock = $this->createMock(RMARepositoryInterface::class);

        $dto = [
            'rmaNumber' => 'RMA-12345',
            'tenantId' => 'tenant-1',
            'customerId' => 'cust-1',
            'locationId' => 'LOC-1',
            'items' => [
                [
                    'variantId' => 'var-1',
                    'quantity' => 2,
                    'unitCostCents' => 1000,
                ],
            ],
        ];

        $repositoryMock->expects($this->once())
            ->method('findByNumber')
            ->with('RMA-12345')
            ->willReturn(null);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RMA $rma) use ($dto) {
                return $rma->getRmaNumber() === $dto['rmaNumber'] &&
                       $rma->getTenantId()->getValue() === $dto['tenantId'] &&
                       $rma->getCustomerId() === $dto['customerId'] &&
                       $rma->getLocationId()->getValue() === $dto['locationId'] &&
                       $rma->getStatus() === RMAStatus::Requested &&
                       count($rma->getItems()) === 1 &&
                       $rma->getItems()[0]->getVariantId() === 'var-1' &&
                       $rma->getItems()[0]->getQuantity() === 2 &&
                       $rma->getItems()[0]->getUnitCostCents() === 1000;
            }));

        $useCase = new CreateRMA($repositoryMock);
        $rma = $useCase->execute($dto);

        $this->assertInstanceOf(RMA::class, $rma);
        $this->assertEquals('RMA-12345', $rma->getRmaNumber());
        $this->assertEquals('tenant-1', $rma->getTenantId()->getValue());
        $this->assertEquals('cust-1', $rma->getCustomerId());
        $this->assertEquals('LOC-1', $rma->getLocationId()->getValue());
        $this->assertEquals(RMAStatus::Requested, $rma->getStatus());
        $this->assertCount(1, $rma->getItems());
        $this->assertEquals('var-1', $rma->getItems()[0]->getVariantId());
        $this->assertEquals(2, $rma->getItems()[0]->getQuantity());
        $this->assertEquals(1000, $rma->getItems()[0]->getUnitCostCents());
    }
}
