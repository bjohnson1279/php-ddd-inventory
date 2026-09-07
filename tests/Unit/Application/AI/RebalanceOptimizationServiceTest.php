<?php

namespace InventoryApp\Application\AI {
    // Mock file_get_contents in the namespace of the tested class
    function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $length = null) {
        if (strpos($filename, '/rebalance-optimize') !== false) {
            // Check if we want to simulate a failure
            if (getenv('SIMULATE_SIDECAR_FAILURE') === '1') {
                return false;
            }
            if (getenv('SIMULATE_SIDECAR_INVALID_JSON') === '1') {
                return 'invalid json';
            }
            return json_encode([
                'recommendations' => [
                    [
                        'sku' => 'SKU-123',
                        'source_warehouse_id' => 'WH1',
                        'dest_warehouse_id' => 'WH2',
                        'quantity' => 20,
                        'priority' => 'HIGH',
                        'estimated_shipping_cost' => 30.0,
                        'source_current_doc' => 45,
                        'dest_current_doc' => 0,
                        'source_projected_doc' => 35,
                        'dest_projected_doc' => 14,
                        'urgency_reason' => 'Stockout predicted'
                    ]
                ],
                'matrix' => ['some' => 'matrix data'],
                'summary' => [
                    'total_transfers' => 1,
                    'total_cost' => 30.0,
                    'skus_improved' => 1,
                    'avg_doc_improvement' => 14.0
                ]
            ]);
        }

        return \file_get_contents($filename, $use_include_path, $context, $offset, $length);
    }
}

namespace Tests\Unit\Application\AI {

    use PHPUnit\Framework\TestCase;
    use InventoryApp\Application\AI\RebalanceOptimizationService;
    use InventoryApp\Infrastructure\Persistence\SqliteSetup;
    use Illuminate\Database\Capsule\Manager as DB;

    require_once __DIR__ . '/../../../../src/Infrastructure/Persistence/sqlite_setup.php';

    class RebalanceOptimizationServiceTest extends TestCase
    {
        protected function setUp(): void
        {
            try {
                DB::connection();
            } catch (\Throwable $e) {
                $capsule = new DB();
                $capsule->addConnection([
                    'driver'   => 'sqlite',
                    'database' => ':memory:',
                    'prefix'   => '',
                ]);
                $capsule->setAsGlobal();
                $capsule->bootEloquent();
            }

            SqliteSetup::createSchema(DB::connection());

            DB::table('warehouse_locations')->delete();
            DB::table('inventory_items')->delete();

            putenv('PYTHON_SIDECAR_URL=http://test-sidecar:5005');
            putenv('SIMULATE_SIDECAR_FAILURE=0');
            putenv('SIMULATE_SIDECAR_INVALID_JSON=0');
        }

        protected function tearDown(): void
        {
            putenv('PYTHON_SIDECAR_URL');
            putenv('SIMULATE_SIDECAR_FAILURE');
            putenv('SIMULATE_SIDECAR_INVALID_JSON');
            parent::tearDown();
        }

        public function testFallbackWhenOnlyOneWarehouse()
        {
            DB::table('warehouse_locations')->insert([
                'id' => 'loc-1',
                'warehouse_id' => 'WH1',
                'zone' => 'US-East',
                'aisle' => 'A',
                'rack' => '1',
                'shelf' => '1',
                'bin' => '1',
                'max_weight_grams' => 1000,
                'max_volume_cubic_meters' => 100,
            ]);

            $service = new RebalanceOptimizationService();
            $result = $service->getMatrix('t1');

            $this->assertEquals([], $result['recommendations']);
            $this->assertEquals([], $result['matrix']);
            $this->assertEquals(0, $result['summary']['totalTransfers']);
        }

        public function testFallbackWhenSidecarRequestFails()
        {
            $this->seedTwoWarehouses();

            putenv('SIMULATE_SIDECAR_FAILURE=1');

            $service = new RebalanceOptimizationService();
            $result = $service->getMatrix('t1');

            $this->assertEquals([], $result['recommendations']);
            $this->assertEquals([], $result['matrix']);
            $this->assertEquals(0, $result['summary']['totalTransfers']);
        }

        public function testFallbackWhenSidecarReturnsInvalidJson()
        {
            $this->seedTwoWarehouses();

            putenv('SIMULATE_SIDECAR_INVALID_JSON=1');

            $service = new RebalanceOptimizationService();
            $result = $service->getMatrix('t1');

            $this->assertEquals([], $result['recommendations']);
        }

        public function testMatrixReturnedWhenSidecarSucceeds()
        {
            $this->seedTwoWarehouses();

            DB::table('inventory_items')->insert([
                ['id' => 'inv-1', 'sku' => 'SKU-123', 'location_id' => 'loc-1', 'quantity' => 100, 'allocated' => 10, 'in_transit' => 0],
                ['id' => 'inv-2', 'sku' => 'SKU-123', 'location_id' => 'loc-2', 'quantity' => 5, 'allocated' => 5, 'in_transit' => 0],
            ]);

            $service = new RebalanceOptimizationService();
            $result = $service->getMatrix('t1');

            $this->assertCount(1, $result['recommendations']);
            $this->assertEquals('SKU-123', $result['recommendations'][0]['sku']);
            $this->assertEquals('WH1', $result['recommendations'][0]['sourceWarehouseId']);
            $this->assertEquals('WH2', $result['recommendations'][0]['destWarehouseId']);
            $this->assertEquals(20, $result['recommendations'][0]['quantity']);

            $this->assertEquals(['some' => 'matrix data'], $result['matrix']);

            $this->assertEquals(1, $result['summary']['totalTransfers']);
            $this->assertEquals(30.0, $result['summary']['totalCost']);
        }

        private function seedTwoWarehouses(): void
        {
            DB::table('warehouse_locations')->insert([
                [
                    'id' => 'loc-1',
                    'warehouse_id' => 'WH1',
                    'zone' => 'US-East',
                    'aisle' => 'A', 'rack' => '1', 'shelf' => '1', 'bin' => '1',
                    'max_weight_grams' => 1000, 'max_volume_cubic_meters' => 100,
                ],
                [
                    'id' => 'loc-2',
                    'warehouse_id' => 'WH2',
                    'zone' => 'US-West',
                    'aisle' => 'A', 'rack' => '1', 'shelf' => '1', 'bin' => '1',
                    'max_weight_grams' => 1000, 'max_volume_cubic_meters' => 100,
                ]
            ]);
        }
    }
}
