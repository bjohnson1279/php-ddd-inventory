<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class FefoRecallE2ETest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        $output = [];
        $command = "php -S 127.0.0.1:8091 public/index.php > tests/Integration/Http/server_fefo.log 2>&1 & echo $!";

        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);

        $command = "php -S 127.0.0.1:8096 public/index.php > tests/Integration/Http/server_fefo.log 2>&1 & echo $!";
        
        
        // Wait for server to bind
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8091, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                break;
            }
            usleep(50000); // 50ms
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
        $suffix = bin2hex(random_bytes(4));
        $this->tenantId = 'tenant-' . $suffix;
        $this->email = 'admin-' . $suffix . '@example.com';
        $this->password = 'SecurePassword123';

        // 1. Initial Setup
        $setupRes = $this->request('POST', '/api/setup', [
            'orgName'       => 'Test Org ' . $suffix,
            'tenantId'      => $this->tenantId,
            'adminName'     => 'Admin User',
            'adminEmail'    => $this->email,
            'adminPassword' => $this->password,
        ]);

        $this->assertEquals(200, $setupRes['status']);

        // 2. Login to get token
        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => $this->email,
            'password'  => $this->password,
        ]);

        $this->assertEquals(200, $loginRes['status']);
        $this->token = $loginRes['body']['token'];
    }

    public function testFefoPickingAndProductRecall(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $productId = uuidv4();
        $sku = 'FEFO-SKU-' . $suffix;

        // 1. Seed Catalog Product and Variant in Database
        DB::table('catalog_products')->insert([
            'id' => $productId,
            'name' => 'FEFO Recall Test Product',
            'description' => 'Test',
            'department' => 'GEN',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table('catalog_variants')->insert([
            'id' => $productId, // in this app variant ID is often product ID or similar, but SKU is unique
            'product_id' => $productId,
            'sku' => $sku,
            'attributes' => '[]',
            'price' => 15.0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Seed domain product and location
        DB::table('products')->insert([
            'id'                => $productId,
            'tenant_id'         => $this->tenantId,
            'sku'               => $sku,
            'name'              => 'FEFO Recall Test Product',
            'department'        => 'GEN',
            'reorder_threshold' => 5,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        DB::table('product_locations')->insert([
            'product_id'        => $productId,
            'location_id'       => 'LOC-INT',
            'stock_quantity'    => 0,
            'open_box_quantity' => 0,
            'damaged_quantity'  => 0,
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        // 2. Receive Stock for Lot 1 (Expires later)
        $receiveLot1 = $this->request('POST', '/api/inventory/receive', [
            'sku'         => $sku,
            'quantity'    => 10,
            'location_id' => 'LOC-INT',
            'lot_number'  => 'LOT-LATE-01',
            'expiration_date' => '2026-12-31 23:59:59',
            'unit_cost_cents' => 1000
        ], $this->token);
        $this->assertEquals(200, $receiveLot1['status'], json_encode($receiveLot1));

        // 3. Receive Stock for Lot 2 (Expires earlier)
        $receiveLot2 = $this->request('POST', '/api/inventory/receive', [
            'sku'         => $sku,
            'quantity'    => 20,
            'location_id' => 'LOC-INT',
            'lot_number'  => 'LOT-EARLY-02',
            'expiration_date' => '2026-06-30 23:59:59',
            'unit_cost_cents' => 1200
        ], $this->token);
        $this->assertEquals(200, $receiveLot2['status'], json_encode($receiveLot2));

        // 4. Request FEFO picking suggestions
        $suggestRes = $this->request('GET', "/api/inventory/fefo-pick?sku={$sku}&quantity=15", [], $this->token);
        $this->assertEquals(200, $suggestRes['status'], json_encode($suggestRes));

        // Assert suggestions prioritize LOT-EARLY-02
        $suggestions = $suggestRes['body'];
        $this->assertCount(1, $suggestions);
        $this->assertEquals('LOT-EARLY-02', $suggestions[0]['lotNumber']);
        $this->assertEquals(15, $suggestions[0]['quantity']);
        $this->assertEquals('LOC-INT', $suggestions[0]['locationId']);

        // 5. Dispatch Stock from Lot 2 (LOT-EARLY-02)
        $dispatchRes = $this->request('POST', '/api/inventory/dispatch', [
            'sku'         => $sku,
            'quantity'    => 15,
            'location_id' => 'LOC-INT',
            'lot_number'  => 'LOT-EARLY-02'
        ], $this->token);
        $this->assertEquals(200, $dispatchRes['status'], json_encode($dispatchRes));

        // 6. Trace Product Recall for LOT-EARLY-02
        $recallRes = $this->request('GET', '/api/reports/recall/LOT-EARLY-02', [], $this->token);
        $this->assertEquals(200, $recallRes['status'], json_encode($recallRes));

        // Assert contaminated dispatch is listed
        $dispatches = $recallRes['body'];
        $this->assertCount(1, $dispatches);
        $this->assertEquals('LOC-INT', $dispatches[0]['locationId']);
        $this->assertEquals(15, $dispatches[0]['quantity']);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8091' . $path;
        $url = 'http://127.0.0.1:8096' . $path;
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

        return [
            'status' => $statusCode,
            'body'   => json_decode((string)$result, true) ?: $result
        ];
    }
}





{
    private static $serverProcess = null;

        public static function setUpBeforeClass(): void
    {
        $baseDir = realpath(__DIR__ . '/../../..');
        $dbPath = $baseDir . '/storage/data/test_feforecalle2etest.sqlite';
        if (!file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            @touch($dbPath);
        }
        $extDir = 'C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext';
        $phpExec = PHP_BINARY . ' -d extension_dir="C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" -d extension=pdo -d extension=mbstring -d extension=pdo_sqlite';
        $cmd = $phpExec . ' -S 127.0.0.1:8093 public/index.php';
        
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", __DIR__ . '/server_feforecalle2etest.log', "a"],
            2 => ["file", __DIR__ . '/server_feforecalle2etest.log', "a"],
        
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
        
            }
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
        $url = 'http://127.0.0.1:8093' . $path;

        }

        
        }

    }
}
