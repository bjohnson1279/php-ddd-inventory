<?php

namespace Tests\Unit\Domain\Accounting\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Accounting\Services\CostLayerService;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown;
use DomainException;

class CostLayerServiceTest extends TestCase
{
    private $repo;
    private $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(CostLayerRepositoryInterface::class);
        $this->service = new CostLayerService($this->repo);
    }

    public function testConsumeFifoLayersUsesOldestFirst(): void
    {
        $layer1 = new InventoryCostLayer('l1', 'v1', 't1', 10, 1000, new \DateTimeImmutable('2026-01-01')); // $10.00
        $layer2 = new InventoryCostLayer('l2', 'v1', 't1', 10, 1200, new \DateTimeImmutable('2026-02-01')); // $12.00

        $this->repo->method('getActiveLayers')->willReturn([$layer1, $layer2]);
        $this->repo->expects($this->once())->method('saveBatch')->with([$layer1, $layer2]);

        // Consume 15 units: 10 from layer 1 ($100), 5 from layer 2 ($60) -> $160
        $breakdown = $this->service->consumeFifoLayers('v1', 15);

        $this->assertEquals(15, $breakdown->units);
        $this->assertEquals(16000, $breakdown->totalCostCents);
        $this->assertEquals(0, $layer1->remainingQuantity());
        $this->assertEquals(5, $layer2->remainingQuantity());
    }

    public function testConsumeFifoLayersThrowsWhenInsufficientStock(): void
    {
        $layer1 = new InventoryCostLayer('l1', 'v1', 't1', 5, 1000, new \DateTimeImmutable());
        $this->repo->method('getActiveLayers')->willReturn([$layer1]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient cost layers/');

        $this->service->consumeFifoLayers('v1', 10);
    }

    public function testWeightedAverageCostCalculation(): void
    {
        $layer1 = new InventoryCostLayer('l1', 'v1', 't1', 10, 1000, new \DateTimeImmutable()); // 10 @ 1000 = 10000
        $layer2 = new InventoryCostLayer('l2', 'v1', 't1', 10, 2000, new \DateTimeImmutable()); // 10 @ 2000 = 20000
        // Total 20 units, Total value 30000 -> Avg 1500

        $this->repo->method('getActiveLayers')->willReturn([$layer1, $layer2]);

        $breakdown = $this->service->calculateWeightedAverageCost('v1', 5);

        $this->assertEquals(5, $breakdown->units);
        $this->assertEquals(7500, $breakdown->totalCostCents); // 5 * 1500
    }

    public function testWeightedAverageCostThrowsWhenNoLayers(): void
    {
        $this->repo->method('getActiveLayers')->willReturn([]);

        $this->expectException(DomainException::class);
        $this->service->calculateWeightedAverageCost('v1', 1);
    }

    public function testConsumeLifoLayersUsesNewestFirst(): void
    {
        $layer1 = new InventoryCostLayer('l1', 'v1', 't1', 10, 1000, new \DateTimeImmutable('2026-01-01')); // $10.00
        $layer2 = new InventoryCostLayer('l2', 'v1', 't1', 10, 1200, new \DateTimeImmutable('2026-02-01')); // $12.00

        $this->repo->method('getActiveLayers')->willReturn([$layer2, $layer1]);
        $this->repo->expects($this->once())->method('saveBatch')->with([$layer2, $layer1]);

        // Consume 15 units: 10 from layer 2 ($120), 5 from layer 1 ($50) -> $170
        $breakdown = $this->service->consumeLifoLayers('v1', 15);

        $this->assertEquals(15, $breakdown->units);
        $this->assertEquals(17000, $breakdown->totalCostCents);
        $this->assertEquals(5, $layer1->remainingQuantity());
        $this->assertEquals(0, $layer2->remainingQuantity());
    }

    public function testConsumeLifoLayersThrowsWhenInsufficientStock(): void
    {
        $layer1 = new InventoryCostLayer('l1', 'v1', 't1', 5, 1000, new \DateTimeImmutable());
        $this->repo->method('getActiveLayers')->willReturn([$layer1]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient cost layers/');

        $this->service->consumeLifoLayers('v1', 10);
    }

    public function testConsumeSpecificLayers(): void
    {
        $layer1 = new InventoryCostLayer('l1', 'v1', 't1', 1, 1500, new \DateTimeImmutable());
        $layer1->serialNumber = 'SN-100';
        $layer2 = new InventoryCostLayer('l2', 'v1', 't1', 1, 2000, new \DateTimeImmutable());
        $layer2->serialNumber = 'SN-200';

        $this->repo->method('findBySerials')
            ->with('v1', ['SN-100', 'SN-200'])
            ->willReturn([$layer1, $layer2]);

        $this->repo->expects($this->once())->method('saveBatch')->with([$layer1, $layer2]);

        $breakdown = $this->service->consumeSpecificLayers('v1', ['SN-100', 'SN-200']);

        $this->assertEquals(2, $breakdown->units);
        $this->assertEquals(3500, $breakdown->totalCostCents);
        $this->assertEquals(0, $layer1->remainingQuantity());
        $this->assertEquals(0, $layer2->remainingQuantity());
    }

    public function testConsumeSpecificLayersThrowsWhenNotFound(): void
    {
        $this->repo->method('findBySerials')->willReturn([]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/No cost layer found for serial number/');

        $this->service->consumeSpecificLayers('v1', ['SN-999']);
    }

    public function testConsumeSpecificLayersThrowsWhenAlreadyConsumed(): void
    {
        $layer = new InventoryCostLayer('l1', 'v1', 't1', 1, 1500, new \DateTimeImmutable());
        $layer->serialNumber = 'SN-100';
        $layer->consume(1); // Already consumed

        $this->repo->method('findBySerials')->willReturn([$layer]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/already been consumed/');

        $this->service->consumeSpecificLayers('v1', ['SN-100']);
    }
}
