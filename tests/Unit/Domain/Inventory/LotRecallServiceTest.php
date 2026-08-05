<?php

namespace Tests\Unit\Domain\Inventory;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Entities\LotBatch;
use InventoryApp\Domain\Inventory\Services\LotRecallService;
use InventoryApp\Domain\Shipping\Services\CrossDockingEngine;
use DateTimeImmutable;

class LotRecallServiceTest extends TestCase
{
    public function testLotAvailabilityAndQuarantine(): void
    {
        $lot = new LotBatch('l-1', 't-1', 'LOT-100', 'VAR-50');
        $this->assertTrue($lot->isAvailable());

        $lot->quarantine('Packaging leak');
        $this->assertEquals('QUARANTINED', $lot->status);
        $this->assertFalse($lot->isAvailable());

        $lot->release();
        $this->assertTrue($lot->isAvailable());
    }

    public function testTraceabilityReportGeneration(): void
    {
        $lot = new LotBatch('l-1', 't-1', 'LOT-100', 'VAR-50', 'RECALLED');
        $shipments = [
            ['id' => 'SHIP-1', 'destinationAddress' => 'Client Alpha', 'quantity' => 20]
        ];

        $report = LotRecallService::generateTraceabilityReport($lot, [], $shipments);
        $this->assertEquals('LOT-100', $report['lotNumber']);
        $this->assertCount(1, $report['affectedOrders']);
        $this->assertContains('Client Alpha', $report['affectedCustomers']);
    }

    public function testTraceabilityReportGenerationEdgeCases(): void
    {
        $lot = new LotBatch('l-1', 't-1', 'LOT-100', 'VAR-50', 'RECALLED');

        $shipments = [
            ['orderId' => 'SHIP-2', 'customerId' => 'Client Beta'],
            ['someOtherKey' => 'value'],
            ['id' => 'SHIP-3', 'customerId' => 'Client Beta', 'quantity' => 5],
        ];

        $costLayers = [
            ['id' => 'CL-1', 'cost' => 10],
            ['id' => 'CL-2', 'cost' => 12],
        ];

        $report = LotRecallService::generateTraceabilityReport($lot, $costLayers, $shipments);

        $this->assertEquals('LOT-100', $report['lotNumber']);
        $this->assertEquals(2, $report['affectedCostLayersCount']);

        $this->assertCount(3, $report['affectedOrders']);
        $this->assertEquals('SHIP-2', $report['affectedOrders'][0]['orderId']);
        $this->assertEquals(1, $report['affectedOrders'][0]['quantity']);
        $this->assertEquals('order-unknown', $report['affectedOrders'][1]['orderId']);
        $this->assertEquals(1, $report['affectedOrders'][1]['quantity']);
        $this->assertEquals('SHIP-3', $report['affectedOrders'][2]['orderId']);
        $this->assertEquals(5, $report['affectedOrders'][2]['quantity']);

        $this->assertCount(1, $report['affectedCustomers']);
        $this->assertEquals('Client Beta', $report['affectedCustomers'][0]);
    }

    public function testCrossDockingEvaluation(): void
    {
        $inbound = [['variantId' => 'V10', 'quantity' => 100]];
        $backorders = [['orderId' => 'BO-100', 'variantId' => 'V10', 'quantity' => 40, 'priority' => 10]];

        $result = CrossDockingEngine::evaluate('PO-777', $inbound, $backorders);
        $this->assertCount(1, $result);
        $this->assertEquals(40, $result[0]['recommendedCrossDockQuantity']);
    }
}
