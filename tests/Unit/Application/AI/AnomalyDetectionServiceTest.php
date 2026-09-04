<?php

namespace InventoryApp\Application\AI {
    function file_get_contents($filename, $use_include_path = false, $context = null) {
        global $mockFileGetContentsResponse;
        if (isset($mockFileGetContentsResponse) && is_callable($mockFileGetContentsResponse)) {
            $func = $mockFileGetContentsResponse;
            return $func($filename, $use_include_path, $context);
        }
        return false;
    }
}

namespace Tests\Unit\Application\AI {
    use PHPUnit\Framework\TestCase;
    use InventoryApp\Application\AI\AnomalyDetectionService;
    use Illuminate\Database\Capsule\Manager as DB;

    class AnomalyDetectionServiceTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            // Set up DB
            try {
                DB::connection();
            } catch (\Throwable $e) {
                $capsule = new DB;
                $capsule->addConnection([
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]);
                $capsule->setAsGlobal();
                $capsule->bootEloquent();
            }

            try {
                DB::table('ledger_entries')->delete();
            } catch (\Throwable $e) {
                DB::schema()->create('ledger_entries', function ($table) {
                    $table->id();
                    $table->string('tenant_id');
                    $table->string('variant_id')->nullable();
                    $table->string('location_id')->nullable();
                    $table->integer('quantity')->nullable();
                    $table->string('reason')->nullable();
                    $table->string('actor_id')->nullable();
                    $table->timestamp('occurred_at')->nullable();
                    $table->string('reference_id')->nullable();
                });
            }
        }

        protected function tearDown(): void
        {
            global $mockFileGetContentsResponse;
            $mockFileGetContentsResponse = null;

            try {
                DB::table('ledger_entries')->delete();
            } catch (\Throwable $e) {
                // Ignore
            }

            parent::tearDown();
        }

        public function testAnalyzeSuccess()
        {
            DB::table('ledger_entries')->insert([
                'tenant_id' => 'tenant-1',
                'variant_id' => 'sku-1',
                'location_id' => 'loc-1',
                'quantity' => 10,
                'reason' => 'stock_in',
                'occurred_at' => '2023-01-01 10:00:00',
            ]);

            DB::table('ledger_entries')->insert([
                'tenant_id' => 'tenant-1',
                'variant_id' => 'sku-2',
                'location_id' => 'loc-2',
                'quantity' => 5,
                'reason' => 'count_adjustment',
                'occurred_at' => '2023-01-02 10:00:00',
            ]);

            global $mockFileGetContentsResponse;
            $mockFileGetContentsResponse = function ($url, $use_include_path, $context) {
                // Assert the payload in context
                $options = stream_context_get_options($context);
                $content = $options['http']['content'] ?? '';
                $decoded = json_decode($content, true);

                $this->assertIsArray($decoded);
                $this->assertCount(2, $decoded['ledger_entries']);
                $this->assertCount(1, $decoded['cycle_counts']);

                return json_encode([
                    'alerts' => [
                        [
                            'alert_type' => 'THEFT',
                            'severity' => 'HIGH',
                            'confidence' => 0.9,
                            'sku' => 'sku-1',
                            'location_id' => 'loc-1',
                        ]
                    ],
                    'summary' => [
                        'total_critical' => 0,
                        'total_high' => 1,
                        'total_medium' => 0,
                        'total_low' => 0,
                        'overall_risk_score' => 85,
                    ]
                ]);
            };

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertArrayHasKey('alerts', $result);
            $this->assertCount(1, $result['alerts']);
            $this->assertEquals('THEFT', $result['alerts'][0]['alertType']);
            $this->assertEquals('HIGH', $result['alerts'][0]['severity']);
            $this->assertEquals('sku-1', $result['alerts'][0]['sku']);

            $this->assertEquals(0, $result['totalCritical']);
            $this->assertEquals(1, $result['totalHigh']);
            $this->assertEquals(85, $result['overallRiskScore']);
        }

        public function testAnalyzeFallbackOnHttpFailure()
        {
            global $mockFileGetContentsResponse;
            $mockFileGetContentsResponse = function ($url, $use_include_path, $context) {
                return false;
            };

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertEmpty($result['alerts']);
            $this->assertEquals(0, $result['totalCritical']);
            $this->assertEquals(0, $result['overallRiskScore']);
        }

        public function testAnalyzeFallbackOnInvalidJson()
        {
            global $mockFileGetContentsResponse;
            $mockFileGetContentsResponse = function ($url, $use_include_path, $context) {
                return "invalid json";
            };

            $service = new AnomalyDetectionService();
            $result = $service->analyze('tenant-1');

            $this->assertEmpty($result['alerts']);
            $this->assertEquals(0, $result['totalCritical']);
            $this->assertEquals(0, $result['overallRiskScore']);
        }
    }
}
