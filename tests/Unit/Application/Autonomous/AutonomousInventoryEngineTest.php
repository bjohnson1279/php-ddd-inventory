<?php

namespace Tests\Unit\Application\Autonomous;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Autonomous\AutonomousInventoryEngine;

class AutonomousInventoryEngineTest extends TestCase
{
    public function testEvaluateStockLevelsNoActionNeeded()
    {
        $engine = new AutonomousInventoryEngine();
        $items = [
            [
                'sku' => 'SKU-001',
                'current_stock' => 15,
                'reorder_point' => 10,
            ]
        ];

        $actions = $engine->evaluateStockLevels($items);

        $this->assertEmpty($actions);
    }

    public function testEvaluateStockLevelsReorderNeededHumanInTheLoop()
    {
        $engine = new AutonomousInventoryEngine('HUMAN_IN_THE_LOOP');
        $items = [
            [
                'sku' => 'SKU-002',
                'current_stock' => 5,
                'reorder_point' => 10,
            ]
        ];

        $actions = $engine->evaluateStockLevels($items);

        $this->assertCount(1, $actions);
        $this->assertEquals('SKU-002', $actions[0]['sku']);
        $this->assertEquals('AUTO_PO', $actions[0]['action']);
        $this->assertEquals(15, $actions[0]['order_quantity']); // (10 * 2) - 5
        $this->assertEquals('HUMAN_IN_THE_LOOP', $actions[0]['mode']);
        $this->assertEquals('DRAFT', $actions[0]['status']);
    }

    public function testEvaluateStockLevelsReorderNeededFullyAutonomous()
    {
        $engine = new AutonomousInventoryEngine('FULLY_AUTONOMOUS');
        $items = [
            [
                'sku' => 'SKU-003',
                'current_stock' => 10,
                'reorder_point' => 10,
            ]
        ];

        $actions = $engine->evaluateStockLevels($items);

        $this->assertCount(1, $actions);
        $this->assertEquals('SKU-003', $actions[0]['sku']);
        $this->assertEquals('AUTO_PO', $actions[0]['action']);
        $this->assertEquals(10, $actions[0]['order_quantity']); // (10 * 2) - 10
        $this->assertEquals('FULLY_AUTONOMOUS', $actions[0]['mode']);
        $this->assertEquals('EXECUTED', $actions[0]['status']);
    }

    public function testEvaluateStockLevelsWithMissingKeys()
    {
        $engine = new AutonomousInventoryEngine();
        $items = [
            [] // Empty array to trigger all defaults: currentStock=0, reorderPoint=10, sku='UNKNOWN'
        ];

        $actions = $engine->evaluateStockLevels($items);

        $this->assertCount(1, $actions);
        $this->assertEquals('UNKNOWN', $actions[0]['sku']);
        $this->assertEquals('AUTO_PO', $actions[0]['action']);
        $this->assertEquals(20, $actions[0]['order_quantity']); // (10 * 2) - 0
        $this->assertEquals('HUMAN_IN_THE_LOOP', $actions[0]['mode']); // Default mode
        $this->assertEquals('DRAFT', $actions[0]['status']);
    }

    public function testEvaluateStockLevelsMultipleItems()
    {
        $engine = new AutonomousInventoryEngine('FULLY_AUTONOMOUS');
        $items = [
            [
                'sku' => 'SKU-OK',
                'current_stock' => 20,
                'reorder_point' => 10,
            ],
            [
                'sku' => 'SKU-LOW',
                'current_stock' => 2,
                'reorder_point' => 5,
            ]
        ];

        $actions = $engine->evaluateStockLevels($items);

        $this->assertCount(1, $actions);
        $this->assertEquals('SKU-LOW', $actions[0]['sku']);
        $this->assertEquals(8, $actions[0]['order_quantity']); // (5 * 2) - 2
        $this->assertEquals('EXECUTED', $actions[0]['status']);
    }
}
