<?php

declare(strict_types=1);

namespace InventoryApp\Application\AI {
    // Mock file_get_contents in the namespace of the service
    function file_get_contents(string $filename, bool $use_include_path = false, $context = null): string|false
    {
        global $mockFileGetContentsResult;

        if (isset($mockFileGetContentsResult)) {
            if ($mockFileGetContentsResult === false) {
                return false;
            }
            return $mockFileGetContentsResult;
        }

        return \file_get_contents($filename, $use_include_path, $context);
    }
}

namespace InventoryApp\Tests\Unit\Application\AI {

    use PHPUnit\Framework\TestCase;
    use InventoryApp\Application\AI\AnomalyDetectionService;
    use Illuminate\Database\Capsule\Manager as DB;

    require_once __DIR__ . '/../../../Integration/bootstrap.php'; // Defines uuidv4()

    /** @group unit */
    final class AnomalyDetectionServiceTest extends TestCase
    {
        protected function setUp(): void
        {
            DB::table('ledger_entries')->delete();
            global $mockFileGetContentsResult;
            $mockFileGetContentsResult = null;
        }

        protected function tearDown(): void
        {
            global $mockFileGetContentsResult;
            $mockFileGetContentsResult = null;
        }

        public function test_analyze_with_no_entries_and_fallback(): void
        {
            $tenantId = 'test-tenant';

            // Set mock to false to simulate a failed HTTP request when there are no entries
            // This prevents a real HTTP request from being made if the service doesn't return early.
            global $mockFileGetContentsResult;
            $mockFileGetContentsResult = false;

            $service = new AnomalyDetectionService();
            $result = $service->analyze($tenantId);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('alerts', $result);
            $this->assertEmpty($result['alerts']);
            $this->assertEquals(0, $result['totalCritical']);
        }

        public function test_analyze_with_mocked_http_call_success(): void
        {
            $tenantId = 'test-tenant';

            DB::table('ledger_entries')->insert([
                [
                    'id' => uuidv4(),
                    'tenant_id' => $tenantId,
                    'variant_id' => 'SKU-1',
                    'quantity' => 10,
                    'reason' => 'count_adjustment',
                    'actor_id' => 'user-1',
                    'occurred_at' => date('Y-m-d H:i:s'),
                    'reference_id' => 'REF-1',
                    'metadata' => '{}',
                ]
            ]);

            global $mockFileGetContentsResult;
            $mockFileGetContentsResult = json_encode([
                'alerts' => [
                    [
                        'alert_type' => 'HIGH_VARIANCE',
                        'severity' => 'HIGH',
                        'confidence' => 0.95,
                        'sku' => 'SKU-1',
                        'location_id' => 'LOC-1',
                        'actor_id' => 'user-1',
                        'title' => 'Test Alert',
                        'description' => 'Test description',
                        'evidence' => ['test'],
                        'detected_at' => date('c'),
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

            $service = new AnomalyDetectionService();

            $result = $service->analyze($tenantId);

            $this->assertCount(1, $result['alerts']);
            $this->assertEquals('HIGH_VARIANCE', $result['alerts'][0]['alertType']);
            $this->assertEquals(1, $result['totalHigh']);
            $this->assertEquals(85, $result['overallRiskScore']);
        }

        public function test_analyze_with_invalid_json_response(): void
        {
            $tenantId = 'test-tenant';

            // Insert entries to ensure the method proceeds to make the HTTP request
            DB::table('ledger_entries')->insert([
                [
                    'id' => uuidv4(),
                    'tenant_id' => $tenantId,
                    'variant_id' => 'SKU-2',
                    'quantity' => 5,
                    'reason' => 'sale',
                    'actor_id' => 'user-2',
                    'occurred_at' => date('Y-m-d H:i:s'),
                    'metadata' => '{}',
                ]
            ]);

            global $mockFileGetContentsResult;
            $mockFileGetContentsResult = 'invalid json data {[';

            $service = new AnomalyDetectionService();
            $result = $service->analyze($tenantId);

            $this->assertEmpty($result['alerts']);
            $this->assertEquals(0, $result['totalCritical']);
        }
    }
}
