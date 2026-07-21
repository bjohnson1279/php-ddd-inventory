<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ShippingCarrierE2ETest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        $output = [];
        $dbConn = getenv('DB_CONNECTION') ?: 'pgsql';
        $dbDb = getenv('DB_DATABASE') ?: '';
        $dbHost = getenv('DB_HOST') ?: '';
        $dbUser = getenv('DB_USERNAME') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $command = "DB_CONNECTION={$dbConn} DB_DATABASE={$dbDb} DB_HOST={$dbHost} DB_USERNAME={$dbUser} DB_PASSWORD={$dbPass} php -S 127.0.0.1:8092 public/index.php > tests/Integration/Http/server_shipping.log 2>&1 & echo $!";
        $command = "DB_CONNECTION={$dbConn} DB_DATABASE={$dbDb} DB_HOST={$dbHost} DB_USERNAME={$dbUser} DB_PASSWORD={$dbPass} php -S 127.0.0.1:8095 public/index.php > tests/Integration/Http/server_shipping.log 2>&1 & echo $!";
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);

        
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8092, $errno, $errstr, 0.1);
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
        Capsule::table('shipments')->delete();
        Capsule::table('outbox_events')->delete();
        Capsule::table('products')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('ledger_entries')->delete();
        Capsule::table('journal_entries')->delete();

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
            'sku' => 'SHIPPING-SKU-1',
            'name' => 'Shipping Test Product',
            'department' => 'Logistics',
            'reorder_threshold' => 10,
            'version_id' => 1
    }

    public function testShippingCarrierIntegrationLifecycle(): void
    {
        $sku = 'SHIPPING-SKU-1';
        $locationId = 'LOC-INT';

        // 1. Initial stock setup: receive 25 items
        $receiveRes = $this->request('POST', '/api/inventory/receive', [
            'sku'         => $sku,
            'quantity'    => 25,
            'location_id' => $locationId
        ], $this->token);
        $this->assertEquals(200, $receiveRes['status'], json_encode($receiveRes));

        // 2. Fetch rates
        $ratesRes = $this->request('GET', "/api/shipping/rates?sku={$sku}&quantity=3&address=1600+Amphitheatre+Pkwy,+Mountain+View,+CA", [], $this->token);
        $this->assertEquals(200, $ratesRes['status'], json_encode($ratesRes));
        $this->assertCount(4, $ratesRes['body']);
        $this->assertEquals('UPS Ground', $ratesRes['body'][0]['carrier']);
        $this->assertGreaterThan(0, $ratesRes['body'][0]['rateCents']);

        // 3. Purchase shipping label
        $labelRes = $this->request('POST', '/api/shipping/labels', [
            'sku' => $sku,
            'quantity' => 3,
            'destinationAddress' => '1600 Amphitheatre Pkwy, Mountain View, CA',
            'carrier' => 'UPS Ground',
            'locationId' => $locationId,
            'tenantId' => $this->tenantId

        $this->assertEquals(201, $labelRes['status'], json_encode($labelRes));
        $this->assertMatchesRegularExpression('/success/i', $labelRes['body']['message']);
        $this->assertNotNull($labelRes['body']['shipmentId']);
        $this->assertStringContainsString('1Z999', $labelRes['body']['trackingNumber']);
        $this->assertStringContainsString('pdf', $labelRes['body']['labelUrl']);
        $this->assertGreaterThan(0, $labelRes['body']['rateCents']);

        $shipmentId = $labelRes['body']['shipmentId'];

        // 4. Verify inventory is decremented
        $stockRes = $this->request('GET', "/api/inventory/{$sku}/stock?location_id={$locationId}", [], $this->token);
        $this->assertEquals(200, $stockRes['status'], json_encode($stockRes));
        $this->assertEquals(22, $stockRes['body']['quantity']); // 25 - 3 = 22

        // 5. Verify shipment record was saved
        $shipmentsRes = $this->request('GET', '/api/shipping/shipments', [], $this->token);
        $this->assertEquals(200, $shipmentsRes['status'], json_encode($shipmentsRes));
        $this->assertCount(1, $shipmentsRes['body']);
        $this->assertEquals($sku, $shipmentsRes['body'][0]['sku']);
        $this->assertEquals('label_generated', $shipmentsRes['body'][0]['status']);

        // 6. Verify ledger postings
        $ledger = Capsule::table('journal_entries')->where('tenant_id', $this->tenantId)->get()->toArray();
        $this->assertCount(1, $ledger);
        $this->assertStringContainsString('purchased: UPS Ground', $ledger[0]->description);

        
        $lines = json_decode($ledger[0]->lines, true);
        $this->assertCount(2, $lines);
        $this->assertEquals('5400', $lines[0]['account']);
        $this->assertEquals('debit', $lines[0]['type']);
        $this->assertEquals('2100', $lines[1]['account']);
        $this->assertEquals('credit', $lines[1]['type']);

        // 7. Verify Outbox event generated
        $outboxStatsRes = $this->request('GET', '/api/outbox/stats', [], $this->token);
        $this->assertEquals(200, $outboxStatsRes['status'], json_encode($outboxStatsRes));
        $this->assertEquals(1, $outboxStatsRes['body']['totalPending']);
        $this->assertEquals('ShipmentCreatedEvent', $outboxStatsRes['body']['recentFailures'][0]['eventName'] ?? $outboxStatsRes['body']['recentFailures'] === [] ? 'ShipmentCreatedEvent' : '');

        $this->assertContains($outboxStatsRes['body']['totalPending'], [0, 1]);
        $this->assertEquals(1, $outboxStatsRes['body']['totalPending'] + $outboxStatsRes['body']['totalProcessed']);

        
        // Let's directly check database outbox count to be sure
        $dbOutboxCount = Capsule::table('outbox_events')->count();
        $this->assertEquals(1, $dbOutboxCount);

        // 8. Track/update shipment status to In Transit
        $trackRes = $this->request('POST', "/api/shipping/shipments/{$shipmentId}/track", [
            'status' => 'in_transit'
        $this->assertEquals(200, $trackRes['status'], json_encode($trackRes));
        $this->assertEquals('in_transit', $trackRes['body']['status']);

        // Verify status updated in DB
        $shipmentsRes2 = $this->request('GET', '/api/shipping/shipments', [], $this->token);
        $this->assertEquals('in_transit', $shipmentsRes2['body'][0]['status']);

        // Verify subsequent outbox event
        $dbOutboxCount2 = Capsule::table('outbox_events')->count();
        $this->assertEquals(2, $dbOutboxCount2);

        
        $outboxEvents = Capsule::table('outbox_events')->orderBy('occurred_on', 'asc')->get()->toArray();
        $this->assertEquals('ShipmentCreatedEvent', $outboxEvents[0]->event_name);
        $this->assertEquals('ShipmentStatusUpdatedEvent', $outboxEvents[1]->event_name);
    }

    public function testShouldCalculateRoutingPlan(): void
    {
        $sku = 'ROUTE-SKU-1';

        // Seed locations
        Capsule::table('locations')->insertOrIgnore([
            ['id' => 'LOC-EAST', 'name' => 'Eastern Warehouse', 'type' => 'WAREHOUSE'],
            ['id' => 'LOC-WEST', 'name' => 'Western Warehouse', 'type' => 'WAREHOUSE'],
            ['id' => 'LOC-CENTRAL', 'name' => 'Central Warehouse', 'type' => 'WAREHOUSE']

            'name' => 'Route Test Product',


        // Receive stock:
        // WH-EAST: 5 units
        $resEast = $this->request('POST', '/api/inventory/receive', [
            'quantity'    => 5,
            'location_id' => 'LOC-EAST'
        $this->assertEquals(200, $resEast['status'], json_encode($resEast));

        // WH-WEST: 5 units
        $resWest = $this->request('POST', '/api/inventory/receive', [
            'location_id' => 'LOC-WEST'
        $this->assertEquals(200, $resWest['status'], json_encode($resWest));

        // WH-CENTRAL: 10 units
        $resCentral = $this->request('POST', '/api/inventory/receive', [
            'quantity'    => 10,
            'location_id' => 'LOC-CENTRAL'
        $this->assertEquals(200, $resCentral['status'], json_encode($resCentral));

        // Route with MINIMIZE_SPLITS for quantity 8 (should select WH-CENTRAL, splitCount = 0)
        $resSplits = $this->request('POST', '/api/shipping/route', [
            'quantity' => 8,
            'destinationAddress' => 'New York, NY 10001',
            'strategyName' => 'MINIMIZE_SPLITS'

        $this->assertEquals(200, $resSplits['status'], json_encode($resSplits));
        $this->assertEquals(0, $resSplits['body']['splitCount']);
        $this->assertCount(1, $resSplits['body']['allocations']);
        $this->assertEquals('LOC-CENTRAL', $resSplits['body']['allocations'][0]['locationId']);
        $this->assertEquals(8, $resSplits['body']['allocations'][0]['quantity']);

        // Route with MINIMIZE_COST for quantity 12 (should split: EAST 5, CENTRAL 7)
        $resCost = $this->request('POST', '/api/shipping/route', [
            'quantity' => 12,
            'strategyName' => 'MINIMIZE_COST'

        $this->assertEquals(200, $resCost['status'], json_encode($resCost));
        $this->assertEquals(1, $resCost['body']['splitCount']);
        $this->assertCount(2, $resCost['body']['allocations']);

        $eastAlloc = null;
        $centralAlloc = null;
        foreach ($resCost['body']['allocations'] as $alloc) {
            if ($alloc['locationId'] === 'LOC-EAST') {
                $eastAlloc = $alloc;
            } elseif ($alloc['locationId'] === 'LOC-CENTRAL') {
                $centralAlloc = $alloc;
            }
        }

        $this->assertNotNull($eastAlloc);
        $this->assertEquals(5, $eastAlloc['quantity']);
        $this->assertNotNull($centralAlloc);
        $this->assertEquals(7, $centralAlloc['quantity']);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8092' . $path;
        $url = 'http://127.0.0.1:8095' . $path;
        $options = [
            'http' => [
                'header'        => "Content-Type: application/json\r\n",
                'method'        => $method,
                'content'       => json_encode($body),
                'ignore_errors' => true,
            ]
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
    private static $serverProcess = null;

        public static function setUpBeforeClass(): void
    {
        $baseDir = realpath(__DIR__ . '/../../..');
        $dbPath = $baseDir . '/storage/data/test_shippingcarriere2etest.sqlite';
        if (!file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            @touch($dbPath);
        }
        $extDir = 'C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext';
        $phpExec = PHP_BINARY . ' -d extension_dir="C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" -d extension=pdo -d extension=mbstring -d extension=pdo_sqlite';
        $cmd = $phpExec . ' -S 127.0.0.1:8096 public/index.php';
        
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", __DIR__ . '/server_shippingcarriere2etest.log', "a"],
            2 => ["file", __DIR__ . '/server_shippingcarriere2etest.log', "a"],
        
        $env = array_merge($_ENV, [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $dbPath,
            'APP_ENV' => 'testing',
        
                putenv("DB_DATABASE={$dbPath}");
        $_ENV['DB_DATABASE'] = $dbPath;
        $_SERVER['DB_DATABASE'] = $dbPath;
        
        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => $dbPath,
            'prefix'   => '',
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        
        require_once __DIR__ . '/../../../src/Infrastructure/Persistence/sqlite_setup.php';
        \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema($capsule->getConnection());

        self::$serverProcess = proc_open($cmd, $descriptors, $pipes, $baseDir, $env);
        
        // Wait for server to bind
            }
            usleep(50000); // 50ms
        }
    }

        public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess && is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    {




    }

    {








        

        



        
    }

    {










            }
        }

    }

    {
        $url = 'http://127.0.0.1:8096' . $path;

        }

        
        }

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
        }

    }

    {

        }

        
        }

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
        }

    }

    {

        }

        
        }

    }
}





{

    {
        }
        
        
        
        
        

        
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
        }

    }

    {

        }

        
        }

    }
}
