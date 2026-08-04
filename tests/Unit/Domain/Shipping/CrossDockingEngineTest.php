<?php

namespace Tests\Unit\Domain\Shipping;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Shipping\Services\CrossDockingEngine;

class CrossDockingEngineTest extends TestCase
{
    public function test_evaluates_empty_inbound_items(): void
    {
        $result = CrossDockingEngine::evaluate('PO-123', [], []);
        $this->assertEmpty($result);
    }

    public function test_no_matching_backorders(): void
    {
        $inbound = [
            ['variantId' => 'V1', 'quantity' => 10]
        ];
        $backorders = [
            ['variantId' => 'V2', 'quantity' => 5, 'orderId' => 'O1']
        ];

        $result = CrossDockingEngine::evaluate('PO-123', $inbound, $backorders);
        $this->assertEmpty($result);
    }

    public function test_exact_match_backorder(): void
    {
        $inbound = [
            ['variantId' => 'V1', 'quantity' => 10]
        ];
        $backorders = [
            ['variantId' => 'V1', 'quantity' => 10, 'orderId' => 'O1', 'priority' => 1]
        ];

        $result = CrossDockingEngine::evaluate('PO-123', $inbound, $backorders);

        $this->assertCount(1, $result);
        $this->assertEquals('PO-123', $result[0]['purchaseOrderId']);
        $this->assertEquals('V1', $result[0]['variantId']);
        $this->assertEquals(10, $result[0]['inboundQuantity']);
        $this->assertEquals(10, $result[0]['recommendedCrossDockQuantity']);
        $this->assertEquals('DOCK-OUTBOUND-BAY-01', $result[0]['destinationBay']);

        $this->assertCount(1, $result[0]['matchingBackorders']);
        $this->assertEquals('O1', $result[0]['matchingBackorders'][0]['orderId']);
        $this->assertEquals(10, $result[0]['matchingBackorders'][0]['requiredQuantity']);
    }

    public function test_partial_fulfillment(): void
    {
        $inbound = [
            ['variantId' => 'V1', 'quantity' => 5]
        ];
        $backorders = [
            ['variantId' => 'V1', 'quantity' => 10, 'orderId' => 'O1', 'priority' => 1]
        ];

        $result = CrossDockingEngine::evaluate('PO-123', $inbound, $backorders);

        $this->assertCount(1, $result);
        $this->assertEquals(5, $result[0]['recommendedCrossDockQuantity']);
        $this->assertEquals(5, $result[0]['matchingBackorders'][0]['requiredQuantity']);
    }

    public function test_excess_inbound_quantity(): void
    {
        $inbound = [
            ['variantId' => 'V1', 'quantity' => 15]
        ];
        $backorders = [
            ['variantId' => 'V1', 'quantity' => 10, 'orderId' => 'O1', 'priority' => 1]
        ];

        $result = CrossDockingEngine::evaluate('PO-123', $inbound, $backorders);

        $this->assertCount(1, $result);
        $this->assertEquals(10, $result[0]['recommendedCrossDockQuantity']);
        $this->assertEquals(10, $result[0]['matchingBackorders'][0]['requiredQuantity']);
    }

    public function test_multiple_backorders_with_prioritization(): void
    {
        $inbound = [
            ['variantId' => 'V1', 'quantity' => 15]
        ];
        $backorders = [
            ['variantId' => 'V1', 'quantity' => 10, 'orderId' => 'O1', 'priority' => 1],
            ['variantId' => 'V1', 'quantity' => 10, 'orderId' => 'O2', 'priority' => 5], // Higher priority
            ['variantId' => 'V1', 'quantity' => 10, 'orderId' => 'O3', 'priority' => 2],
        ];

        $result = CrossDockingEngine::evaluate('PO-123', $inbound, $backorders);

        $this->assertCount(1, $result);
        $this->assertEquals(15, $result[0]['recommendedCrossDockQuantity']);
        $this->assertCount(2, $result[0]['matchingBackorders']);

        // First backorder assigned should be O2 (highest priority = 5)
        $this->assertEquals('O2', $result[0]['matchingBackorders'][0]['orderId']);
        $this->assertEquals(10, $result[0]['matchingBackorders'][0]['requiredQuantity']);

        // Second backorder assigned should be O3 (next highest priority = 2)
        $this->assertEquals('O3', $result[0]['matchingBackorders'][1]['orderId']);
        $this->assertEquals(5, $result[0]['matchingBackorders'][1]['requiredQuantity']);
    }
}
