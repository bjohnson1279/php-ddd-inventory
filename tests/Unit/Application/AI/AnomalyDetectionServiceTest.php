<?php

namespace InventoryApp\Application\AI {
    if (!function_exists('InventoryApp\Application\AI\file_get_contents')) {
        function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $length = null) {
            if (strpos($filename, '/rebalance-optimize') !== false) {
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

            if (strpos($filename, '/anomaly-detect') !== false) {
                if (getenv('SIMULATE_SIDECAR_FAILURE') === '1') {
                    return false;
                }
                if (getenv('SIMULATE_SIDECAR_INVALID_JSON') === '1') {
                    return 'invalid json';
                }
                return json_encode([
                    'alerts' => [
                        [
                            'alert_type' => 'UNUSUAL_VOLUME',
                            'severity' => 'HIGH',
                            'confidence' => 0.85,
                            'sku' => 'SKU-100',
                            'location_id' => 'loc-1',
                            'actor_id' => 'usr-1',
                            'title' => 'High volume adjustment',
                            'description' => 'Adjusted 500 units',
                            'evidence' => ['large delta'],
                            'detected_at' => '2025-01-01T00:00:00Z'
                        ]
                    ],
                    'summary' => [
                        'total_critical' => 0,
                        'total_high' => 1,
                        'total_medium' => 0,
                        'total_low' => 0,
                        'overall_risk_score' => 0.85
                    ]
                ]);
            }

            return \file_get_contents($filename, $use_include_path, $context, $offset, $length);
        }
    }
}

namespace Tests\Unit\Application\AI {

    use PHPUnit\Framework\TestCase;
    use InventoryApp\Application\AI\AnomalyDetectionService;
    use InventoryApp\Infrastructure\Persistence\SqliteSetup;
    use Illuminate\Database\Capsule\Manager as DB;

    require_once __DIR__ . '/../../../../src/Infrastructure/Persistence/sqlite_setup.php';

    class AnomalyDetectionServiceTest extends TestCase
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

            DB::table('ledger_entries')->delete();

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

        public function testAnalyzeSuccess(): void
        {
            DB::table('ledger_entries')->insert([
                'id' => 'leg-1',
                'tenant_id' => 'tenant-1',
                'variant_id' => 'SKU-100',
                'quantity' => 500,
                'reason' => 'manual_adjustment',
                'actor_id' => 'usr-1',
                'reference_id' => 'ref-1',
                'occurred_at' => '2025-01-01 00:00:00',
            ]);

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('alerts', $result);
            $this->assertCount(1, $result['alerts']);

            $alert = $result['alerts'][0];
            $this->assertEquals('UNUSUAL_VOLUME', $alert['alertType']);
            $this->assertEquals('HIGH', $alert['severity']);
            $this->assertEquals(0.85, $alert['confidence']);
            $this->assertEquals('SKU-100', $alert['sku']);
            $this->assertEquals('loc-1', $alert['locationId']);
            $this->assertEquals('usr-1', $alert['actorId']);
            $this->assertEquals('High volume adjustment', $alert['title']);
            $this->assertEquals('Adjusted 500 units', $alert['description']);
            $this->assertEquals(['large delta'], $alert['evidence']);
            $this->assertEquals('2025-01-01T00:00:00Z', $alert['detectedAt']);

            $this->assertEquals(0, $result['totalCritical']);
            $this->assertEquals(1, $result['totalHigh']);
            $this->assertEquals(0, $result['totalMedium']);
            $this->assertEquals(0, $result['totalLow']);
            $this->assertEquals(0.85, $result['overallRiskScore']);
        }

        public function testAnalyzeFallbackWhenSidecarRequestFails(): void
        {
            DB::table('ledger_entries')->insert([
                'id' => 'leg-1',
                'tenant_id' => 'tenant-1',
                'variant_id' => 'SKU-100',
                'quantity' => 10,
                'reason' => 'transfer',
                'actor_id' => 'usr-1',
                'occurred_at' => '2025-01-01 00:00:00',
            ]);

            putenv('SIMULATE_SIDECAR_FAILURE=1');

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertEquals([], $result['alerts']);
            $this->assertEquals(0, $result['totalCritical']);
            $this->assertEquals(0, $result['totalHigh']);
            $this->assertEquals(0, $result['totalMedium']);
            $this->assertEquals(0, $result['totalLow']);
            $this->assertEquals(0, $result['overallRiskScore']);
        }

        public function testAnalyzeFallbackWhenSidecarReturnsInvalidJson(): void
        {
            DB::table('ledger_entries')->insert([
                'id' => 'leg-1',
                'tenant_id' => 'tenant-1',
                'variant_id' => 'SKU-100',
                'quantity' => 10,
                'reason' => 'transfer',
                'actor_id' => 'usr-1',
                'occurred_at' => '2025-01-01 00:00:00',
            ]);

            putenv('SIMULATE_SIDECAR_INVALID_JSON=1');

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertEquals([], $result['alerts']);
            $this->assertEquals(0, $result['totalCritical']);
            $this->assertEquals(0, $result['totalHigh']);
            $this->assertEquals(0, $result['totalMedium']);
            $this->assertEquals(0, $result['totalLow']);
            $this->assertEquals(0, $result['overallRiskScore']);
        }

        public function testAnalyzeWithCycleCountsAndEmptyLedger(): void
        {
            DB::table('ledger_entries')->insert([
                'id' => 'leg-count-1',
                'tenant_id' => 'tenant-1',
                'variant_id' => 'SKU-200',
                'quantity' => -5,
                'reason' => 'count_adjustment',
                'actor_id' => 'usr-2',
                'occurred_at' => '2025-01-01 12:00:00',
            ]);

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertIsArray($result);
            $this->assertCount(1, $result['alerts']);

            // Empty ledger test
            DB::table('ledger_entries')->delete();

            $emptyResult = $service->analyze('tenant-empty');
            $this->assertIsArray($emptyResult);
            $this->assertCount(1, $emptyResult['alerts']);
        }
    }
}
