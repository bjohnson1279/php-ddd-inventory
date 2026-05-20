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
        $this->repo->expects($this->exactly(2))->method('save');

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
}
