<?php

namespace Tests\Unit\Domain\Inventory;

use PHPUnit\Framework\TestCase;
use App\Domain\Inventory\Entities\LotBatch;
use App\Domain\Inventory\Services\LotRecallService;
use App\Domain\Shipping\Services\CrossDockingEngine;
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

    public function testCrossDockingEvaluation(): void
    {
        $inbound = [['variantId' => 'V10', 'quantity' => 100]];
        $backorders = [['orderId' => 'BO-100', 'variantId' => 'V10', 'quantity' => 40, 'priority' => 10]];

        $result = CrossDockingEngine::evaluate('PO-777', $inbound, $backorders);
        $this->assertCount(1, $result);
        $this->assertEquals(40, $result[0]['recommendedCrossDockQuantity']);
    }
}
