<?php

namespace Tests\Unit\Domain\Shipping;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Shipping\ValueObjects\GeoLocation;
use InventoryApp\Domain\Shipping\Services\OrderRoutingEngine;
use InventoryApp\Domain\Shipping\Strategies\MinimizeCostStrategy;
use InventoryApp\Domain\Shipping\Strategies\MinimizeSplitsStrategy;
use InventoryApp\Domain\Shipping\Strategies\MinimizeDistanceStrategy;
use Exception;

class OrderRoutingEngineTest extends TestCase
{
    private GeoLocation $nyDest;
    private array $candidates;

    protected function setUp(): void
    {
        $this->nyDest = new GeoLocation(40.7128, -74.0060);

        $this->candidates = [
            [
                'locationId' => 'WH-EAST',
                'availableQuantity' => 5,
                'geoLocation' => new GeoLocation(40.7306, -73.9352)
            ],
            [
                'locationId' => 'WH-WEST',
                'availableQuantity' => 5,
                'geoLocation' => new GeoLocation(34.0522, -118.2437)
            ],
            [
                'locationId' => 'WH-CENTRAL',
                'availableQuantity' => 10,
                'geoLocation' => new GeoLocation(41.8781, -87.6298)
            ]
        ];
    }

    private function getMockRateCalculator(): callable
    {
        return function (string $locationId, string $sku, int $qty): int {
            $originGeo = $locationId === 'WH-EAST'
                ? new GeoLocation(40.7306, -73.9352)
                : ($locationId === 'WH-WEST'
                    ? new GeoLocation(34.0522, -118.2437)
                    : new GeoLocation(41.8781, -87.6298));

            $dist = $originGeo->distanceTo($this->nyDest);
            return (int)ceil($dist * 0.1) * $qty;
        };
    }

    public function testShouldFailRoutingIfQuantityIsTooHigh()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient total stock");
        OrderRoutingEngine::routeOrder(
            "SKU-1",
            25,
            $this->nyDest,
            $this->candidates,
            new MinimizeCostStrategy(),
            $this->getMockRateCalculator()
        );
    }

    public function testShouldChooseSingleWarehouseWhenMinimizingSplits()
    {
        $plan = OrderRoutingEngine::routeOrder(
            "SKU-1",
            8,
            $this->nyDest,
            $this->candidates,
            new MinimizeSplitsStrategy(),
            $this->getMockRateCalculator()
        );

        $this->assertEquals(0, $plan->splitCount);
        $this->assertCount(1, $plan->allocations);
        $this->assertEquals("WH-CENTRAL", $plan->allocations[0]->locationId);
        $this->assertEquals(8, $plan->allocations[0]->quantity);
    }

    public function testShouldChooseCheaperSplitWhenMinimizingCost()
    {
        $plan = OrderRoutingEngine::routeOrder(
            "SKU-1",
            12,
            $this->nyDest,
            $this->candidates,
            new MinimizeCostStrategy(),
            $this->getMockRateCalculator()
        );

        $this->assertEquals(1, $plan->splitCount);
        $this->assertCount(2, $plan->allocations);

        $eastAlloc = null;
        $centralAlloc = null;
        foreach ($plan->allocations as $alloc) {
            if ($alloc->locationId === 'WH-EAST') {
                $eastAlloc = $alloc;
            } elseif ($alloc->locationId === 'WH-CENTRAL') {
                $centralAlloc = $alloc;
            }
        }

        $this->assertNotNull($eastAlloc);
        $this->assertEquals(5, $eastAlloc->quantity);
        $this->assertNotNull($centralAlloc);
        $this->assertEquals(7, $centralAlloc->quantity);
    }

    public function testShouldChooseClosestLocationWhenMinimizingDistance()
    {
        $plan = OrderRoutingEngine::routeOrder(
            "SKU-1",
            3,
            $this->nyDest,
            $this->candidates,
            new MinimizeDistanceStrategy(),
            $this->getMockRateCalculator()
        );

        $this->assertCount(1, $plan->allocations);
        $this->assertEquals("WH-EAST", $plan->allocations[0]->locationId);
        $this->assertEquals(3, $plan->allocations[0]->quantity);
    }
}
