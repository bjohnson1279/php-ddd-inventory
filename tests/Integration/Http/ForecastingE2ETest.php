<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ForecastingE2ETest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        $output = [];
        $command = "php -S 127.0.0.1:8089 public/index.php > tests/Integration/Http/server_forecasting.log 2>&1 & echo $!";
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);

        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8089, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                break;
            }
            usleep(50000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pid) {
            exec("kill " . self::$pid . " > /dev/null 2>&1");
        }
    }

    protected function setUp(): void
    {
        Capsule::table('demand_forecasts')->delete();
        Capsule::table('reorder_policies')->delete();
        Capsule::table('products')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('ledger_entries')->delete();

        $suffix = bin2hex(random_bytes(4));
        $this->tenantId = 'tenant-' . $suffix;
        $this->email = 'admin-' . $suffix . '@example.com';
        $this->password = 'SecurePassword123';

        $setupRes = $this->request('POST', '/api/setup', [
            'orgName'       => 'Test Org ' . $suffix,
            'tenantId'      => $this->tenantId,
            'adminName'     => 'Admin User',
            'adminEmail'    => $this->email,
            'adminPassword' => $this->password,
        ]);
        $this->assertEquals(200, $setupRes['status']);

        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => $this->email,
            'password'  => $this->password,
        $this->assertEquals(200, $loginRes['status']);
        $this->token = $loginRes['body']['token'];

        // Seed product
        Capsule::table('products')->insert([
            'id' => uuidv4(),
            'sku' => 'IPHONE-15',
            'name' => 'iPhone 15',
            'department' => 'Electronics',
            'reorder_threshold' => 10,
            'version_id' => 1
    }

    public function testForecastingLifecycle(): void
    {
        $sku = 'IPHONE-15';
        $locationId = 'LOC-INT';

        // 1. Initial stock setup: receive 50 items
        $receiveRes = $this->request('POST', '/api/inventory/receive', [
            'sku'         => $sku,
            'quantity'    => 50,
            'location_id' => $locationId
        ], $this->token);
        $this->assertEquals(200, $receiveRes['status'], json_encode($receiveRes));

        // 2. Add historic ledger entries for sale (simulating dispatches)
        // 3 dispatches of size 10 in the last 30 days
        $nowStr = date('Y-m-d H:i:s');
        $twoDaysAgo = date('Y-m-d H:i:s', time() - 2 * 24 * 3600);
        $fiveDaysAgo = date('Y-m-d H:i:s', time() - 5 * 24 * 3600);
        $tenDaysAgo = date('Y-m-d H:i:s', time() - 10 * 24 * 3600);

        Capsule::table('ledger_entries')->insert([
            [
                'id' => uuidv4(),
                'tenant_id' => $this->tenantId,
                'variant_id' => $sku,
                'quantity' => -10,
                'reason' => 'sale',
                'actor_id' => 'system',
                'reference_id' => '1',
                'occurred_at' => $twoDaysAgo,
                'metadata' => json_encode(['locationId' => $locationId]),
                'created_at' => $nowStr,
            ],
                'reference_id' => '2',
                'occurred_at' => $fiveDaysAgo,
                'reference_id' => '3',
                'occurred_at' => $tenDaysAgo,
            ]

        // 3. Request demand planning report
        $reportRes = $this->request('GET', '/api/forecasting/report?locationId=' . $locationId, [], $this->token);
        $this->assertEquals(200, $reportRes['status'], json_encode($reportRes));
        $this->assertCount(1, $reportRes['body']);

        $reportItem = $reportRes['body'][0];
        $this->assertEquals($sku, $reportItem['sku']);
        $this->assertEquals($locationId, $reportItem['locationId']);
        $this->assertEquals(50, $reportItem['currentStock']);

        // 30 units in 30 days -> ADS 30d should be exactly 1.0 (30 / 30)
        $this->assertEquals(1.0, $reportItem['averageDailySales30d']);
        // Days of cover = currentStock (50) / ADS 30d (1.0) = 50 days
        $this->assertEquals(50, $reportItem['daysOfCover']);
        $this->assertNotNull($reportItem['runOutDate']);

        // 4. Generate manual demand forecast via POST
        $forecastRes = $this->request('POST', '/api/forecasting/forecast', [
            'sku' => $sku,
            'locationId' => $locationId,
            'forecastDays' => 15,
            'trendMultiplier' => 1.2

        $this->assertEquals(200, $forecastRes['status'], json_encode($forecastRes));
        $this->assertMatchesRegularExpression('/success/i', $forecastRes['body']['message']);

        $forecast = $forecastRes['body']['forecast'];
        $this->assertEquals($sku, $forecast['sku']);
        $this->assertEquals($locationId, $forecast['locationId']);
        // Projected forecast quantity: Math.ceil(ADS (1.0) * forecastDays (15) * trendMultiplier (1.2)) = Math.ceil(18) = 18.
        $this->assertEquals(18, $forecast['forecastedQuantity']);
        $this->assertEquals(0.85, $forecast['confidenceLevel']);

        // 5. Request report again, it should now reflect active forecast
        $reportRes2 = $this->request('GET', '/api/forecasting/report?locationId=' . $locationId, [], $this->token);
        $this->assertEquals(200, $reportRes2['status']);
        $reportItem2 = $reportRes2['body'][0];
        $this->assertEquals(18, $reportItem2['forecastedDemand30d']);
        $this->assertEquals(0.85, $reportItem2['confidenceLevel']);
    }

    public function testSeasonalForecasting(): void
    {

        $this->assertEquals(200, $receiveRes['status']);

        $now = new \DateTime();
        $nowStr = $now->format('Y-m-d H:i:s');

        $sameMonthLastYear = (new \DateTime())->modify('-364 days');
        $sameMonthLastYearStr = $sameMonthLastYear->format('Y-m-d H:i:s');

        $diffMonthLastYear = (new \DateTime())->modify('-300 days');
        $diffMonthLastYearStr = $diffMonthLastYear->format('Y-m-d H:i:s');

        $recentSaleStr = (new \DateTime())->modify('-5 days')->format('Y-m-d H:i:s');

                'reference_id' => 'rec-1',
                'occurred_at' => $recentSaleStr,
                'quantity' => -30,
                'reference_id' => 'rec-2',
                'occurred_at' => $sameMonthLastYearStr,
                'reference_id' => 'rec-3',
                'occurred_at' => $diffMonthLastYearStr,

            'forecastDays' => 30,
            'trendMultiplier' => 1.0


        $this->assertGreaterThan(10, $forecast['forecastedQuantity']);
        $this->assertEquals(0.90, $forecast['confidenceLevel']);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8089' . $path;
        $options = [
            'http' => [
                'header'        => "Content-Type: application/json\r\n",
                'method'        => $method,
                'content'       => json_encode($body),
                'ignore_errors' => true,
        ];

        if ($token) {
            $options['http']['header'] .= "Authorization: Bearer {$token}\r\n";
        }

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        $statusCode = 500;
        if (isset($http_response_header) && isset($http_response_header[0])) {
            preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
            $statusCode = (int)$match[1];
        }

        $decoded = json_decode((string)$result, true);
        return [
            'status' => $statusCode,
            'body'   => (json_last_error() === JSON_ERROR_NONE) ? $decoded : $result
    }
}





{

    {
        
            }
        }
    }

    {
        }
    }

    {




    }

    {





        


        

    }

    {


        





        
    }

    {

        }

        
        }

    }
}
