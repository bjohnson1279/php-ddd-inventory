<?php

declare(strict_types=1);

namespace InventoryApp\Application\AI;

// We need to define the mock function in the namespace where it's used
function file_get_contents($filename, $use_include_path = false, $context = null) {
    if (isset($GLOBALS['mock_file_get_contents_response'])) {
        $response = $GLOBALS['mock_file_get_contents_response'];
        if ($response instanceof \Closure) {
            return $response($filename, $use_include_path, $context);
        }
        return $response;
    }
    return \file_get_contents($filename, $use_include_path, $context);
}

namespace tests\Unit\Application\AI;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\AI\RebalanceOptimizationService;
use Illuminate\Database\Capsule\Manager as DB;

class RebalanceOptimizationServiceTest extends TestCase
{
    private $capsule;

    protected function setUp(): void
    {
        $this->capsule = new DB;
        $this->capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        DB::statement('CREATE TABLE warehouse_locations (id TEXT, warehouse_id TEXT, zone TEXT)');
        DB::statement('CREATE TABLE inventory_items (sku TEXT, location_id TEXT, quantity INTEGER, allocated INTEGER, in_transit INTEGER)');
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE warehouse_locations');
        DB::statement('DROP TABLE inventory_items');
        unset($GLOBALS['mock_file_get_contents_response']);
    }

    public function test_get_matrix_early_exit_single_warehouse()
    {
        DB::table('warehouse_locations')->insert([
            ['id' => 'L1', 'warehouse_id' => 'W1', 'zone' => 'Z1'],
        ]);

        $service = new RebalanceOptimizationService();
        $result = $service->getMatrix('tenant-1');

        $this->assertEquals([], $result['recommendations']);
        $this->assertEquals([], $result['matrix']);
        $this->assertEquals(0, $result['summary']['totalTransfers']);
    }

    public function test_get_matrix_http_failure_fallback()
    {
        DB::table('warehouse_locations')->insert([
            ['id' => 'L1', 'warehouse_id' => 'W1', 'zone' => 'Z1'],
            ['id' => 'L2', 'warehouse_id' => 'W2', 'zone' => 'Z2'],
        ]);

        DB::table('inventory_items')->insert([
            ['sku' => 'SKU1', 'location_id' => 'L1', 'quantity' => 10, 'allocated' => 2, 'in_transit' => 0],
            ['sku' => 'SKU1', 'location_id' => 'L2', 'quantity' => 0, 'allocated' => 0, 'in_transit' => 0],
        ]);

        $GLOBALS['mock_file_get_contents_response'] = false; // Simulate HTTP failure

        $service = new RebalanceOptimizationService();
        $result = $service->getMatrix('tenant-1');

        $this->assertEquals([], $result['recommendations']);
        $this->assertEquals(0, $result['summary']['totalTransfers']);
    }

    public function test_get_matrix_json_parse_failure_fallback()
    {
        DB::table('warehouse_locations')->insert([
            ['id' => 'L1', 'warehouse_id' => 'W1', 'zone' => 'Z1'],
            ['id' => 'L2', 'warehouse_id' => 'W2', 'zone' => 'Z2'],
        ]);

        DB::table('inventory_items')->insert([
            ['sku' => 'SKU1', 'location_id' => 'L1', 'quantity' => 10, 'allocated' => 2, 'in_transit' => 0],
            ['sku' => 'SKU1', 'location_id' => 'L2', 'quantity' => 0, 'allocated' => 0, 'in_transit' => 0],
        ]);

        $GLOBALS['mock_file_get_contents_response'] = 'INVALID JSON'; // Simulate invalid JSON

        $service = new RebalanceOptimizationService();
        $result = $service->getMatrix('tenant-1');

        $this->assertEquals([], $result['recommendations']);
        $this->assertEquals(0, $result['summary']['totalTransfers']);
    }

    public function test_get_matrix_success()
    {
        DB::table('warehouse_locations')->insert([
            ['id' => 'L1', 'warehouse_id' => 'W1', 'zone' => 'Z1'],
            ['id' => 'L2', 'warehouse_id' => 'W2', 'zone' => 'Z2'],
        ]);

        DB::table('inventory_items')->insert([
            ['sku' => 'SKU1', 'location_id' => 'L1', 'quantity' => 10, 'allocated' => 2, 'in_transit' => 0],
            ['sku' => 'SKU1', 'location_id' => 'L2', 'quantity' => 0, 'allocated' => 0, 'in_transit' => 0],
        ]);

        $GLOBALS['mock_file_get_contents_response'] = function($url, $use_include_path, $context) {
            return json_encode([
                'recommendations' => [
                    [
                        'sku' => 'SKU1',
                        'source_warehouse_id' => 'W1',
                        'dest_warehouse_id' => 'W2',
                        'quantity' => 5,
                        'priority' => 'HIGH',
                        'estimated_shipping_cost' => 7.5,
                        'source_current_doc' => 20,
                        'dest_current_doc' => 0,
                        'source_projected_doc' => 10,
                        'dest_projected_doc' => 10,
                        'urgency_reason' => 'Stockout',
                    ]
                ],
                'matrix' => ['some_matrix_data'],
                'summary' => [
                    'total_transfers' => 1,
                    'total_cost' => 7.5,
                    'skus_improved' => 1,
                    'avg_doc_improvement' => 10,
                ]
            ]);
        };

        $service = new RebalanceOptimizationService();
        $result = $service->getMatrix('tenant-1');

        $this->assertCount(1, $result['recommendations']);
        $this->assertEquals('SKU1', $result['recommendations'][0]['sku']);
        $this->assertEquals('W1', $result['recommendations'][0]['sourceWarehouseId']);
        $this->assertEquals('W2', $result['recommendations'][0]['destWarehouseId']);
        $this->assertEquals(['some_matrix_data'], $result['matrix']);
        $this->assertEquals(1, $result['summary']['totalTransfers']);
        $this->assertEquals(7.5, $result['summary']['totalCost']);
    }
}
