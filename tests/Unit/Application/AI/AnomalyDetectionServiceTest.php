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

                if ($context !== null) {
                    $opts = stream_context_get_options($context);
                    if (isset($opts['http']['content'])) {
                        $GLOBALS['LAST_SIDECAR_PAYLOAD'] = json_decode($opts['http']['content'], true);
                    }
                }

                return json_encode([
                    'alerts' => [
                        [
                            'alert_type' => 'HIGH_VARIANCE',
                            'severity' => 'HIGH',
                            'confidence' => 0.95,
                            'sku' => 'SKU-001',
                            'location_id' => 'LOC-1',
                            'actor_id' => 'USER-1',
                            'title' => 'Unusual Count Adjustment',
                            'description' => 'Discrepancy detected in cycle count',
                            'evidence' => ['expected' => 0, 'actual' => 50],
                            'detected_at' => '2025-01-01T00:00:00Z',
                        ]
                    ],
                    'summary' => [
                        'total_critical' => 0,
                        'total_high' => 1,
                        'total_medium' => 0,
                        'total_low' => 0,
                        'overall_risk_score' => 80.0,
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
            unset($GLOBALS['LAST_SIDECAR_PAYLOAD']);
        }

        protected function tearDown(): void
        {
            putenv('PYTHON_SIDECAR_URL');
            putenv('SIMULATE_SIDECAR_FAILURE');
            putenv('SIMULATE_SIDECAR_INVALID_JSON');
            unset($GLOBALS['LAST_SIDECAR_PAYLOAD']);
            parent::tearDown();
        }

        public function testAnalyzeSuccess(): void
        {
            DB::table('ledger_entries')->insert([
                'id' => 'entry-1',
                'tenant_id' => 'tenant-1',
                'variant_id' => 'SKU-001',
                'quantity' => 10,
                'reason' => 'sale',
                'actor_id' => 'USER-1',
                'reference_id' => 'REF-1',
                'occurred_at' => date('Y-m-d H:i:s'),
            ]);

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertCount(1, $result['alerts']);
            $alert = $result['alerts'][0];
            $this->assertEquals('HIGH_VARIANCE', $alert['alertType']);
            $this->assertEquals('HIGH', $alert['severity']);
            $this->assertEquals(0.95, $alert['confidence']);
            $this->assertEquals('SKU-001', $alert['sku']);
            $this->assertEquals('LOC-1', $alert['locationId']);
            $this->assertEquals('USER-1', $alert['actorId']);
            $this->assertEquals('Unusual Count Adjustment', $alert['title']);
            $this->assertEquals('Discrepancy detected in cycle count', $alert['description']);
            $this->assertEquals(['expected' => 0, 'actual' => 50], $alert['evidence']);
            $this->assertEquals('2025-01-01T00:00:00Z', $alert['detectedAt']);

            $this->assertEquals(0, $result['totalCritical']);
            $this->assertEquals(1, $result['totalHigh']);
            $this->assertEquals(0, $result['totalMedium']);
            $this->assertEquals(0, $result['totalLow']);
            $this->assertEquals(80.0, $result['overallRiskScore']);
        }

        public function testAnalyzeWithCycleCounts(): void
        {
            DB::table('ledger_entries')->insert([
                'id' => 'entry-2',
                'tenant_id' => 'tenant-1',
                'variant_id' => 'SKU-002',
                'quantity' => 5,
                'reason' => 'count_adjustment',
                'actor_id' => 'USER-2',
                'reference_id' => 'REF-2',
                'occurred_at' => '2025-01-01 10:00:00',
            ]);

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('LAST_SIDECAR_PAYLOAD', $GLOBALS);
            $payload = $GLOBALS['LAST_SIDECAR_PAYLOAD'];

            $this->assertCount(1, $payload['ledger_entries']);
            $this->assertEquals('SKU-002', $payload['ledger_entries'][0]['sku']);

            $this->assertCount(1, $payload['cycle_counts']);
            $this->assertEquals('SKU-002', $payload['cycle_counts'][0]['sku']);
            $this->assertEquals(5, $payload['cycle_counts'][0]['counted_quantity']);
            $this->assertEquals('USER-2', $payload['cycle_counts'][0]['actor_id']);
        }

        public function testAnalyzeTenantIsolation(): void
        {
            DB::table('ledger_entries')->insert([
                'id' => 'entry-tenant-1',
                'tenant_id' => 'tenant-1',
                'variant_id' => 'SKU-TENANT-1',
                'quantity' => 1,
                'reason' => 'sale',
                'actor_id' => 'USER-1',
                'reference_id' => null,
                'occurred_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('ledger_entries')->insert([
                'id' => 'entry-tenant-2',
                'tenant_id' => 'tenant-2',
                'variant_id' => 'SKU-TENANT-2',
                'quantity' => 100,
                'reason' => 'sale',
                'actor_id' => 'USER-2',
                'reference_id' => null,
                'occurred_at' => date('Y-m-d H:i:s'),
            ]);

            $service = new AnomalyDetectionService();
            $service->analyze('tenant-1');

            $this->assertArrayHasKey('LAST_SIDECAR_PAYLOAD', $GLOBALS);
            $payload = $GLOBALS['LAST_SIDECAR_PAYLOAD'];

            $this->assertCount(1, $payload['ledger_entries']);
            $this->assertEquals('SKU-TENANT-1', $payload['ledger_entries'][0]['sku']);
        }

        public function testAnalyzeSidecarFailure(): void
        {
            putenv('SIMULATE_SIDECAR_FAILURE=1');

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertEquals([
                'alerts' => [],
                'totalCritical' => 0,
                'totalHigh' => 0,
                'totalMedium' => 0,
                'totalLow' => 0,
                'overallRiskScore' => 0,
            ], $result);
        }

        public function testAnalyzeInvalidJsonResponse(): void
        {
            putenv('SIMULATE_SIDECAR_INVALID_JSON=1');

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertEquals([
                'alerts' => [],
                'totalCritical' => 0,
                'totalHigh' => 0,
                'totalMedium' => 0,
                'totalLow' => 0,
                'overallRiskScore' => 0,
            ], $result);
        }
    }
}
