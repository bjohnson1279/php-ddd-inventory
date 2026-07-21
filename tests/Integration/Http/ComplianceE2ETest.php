<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ComplianceE2ETest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        $output = [];
        // Ensure environment variables pass through to the PHP dev server
        $dbConnection = getenv('DB_CONNECTION') ?: 'pgsql';
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbPort = getenv('DB_PORT') ?: '5432';
        $dbDatabase = getenv('DB_DATABASE') ?: 'ddd_inventory';
        $dbUsername = getenv('DB_USERNAME') ?: 'ddd_user';
        $dbPassword = getenv('DB_PASSWORD') ?: 'secret';

        if ($dbConnection === 'sqlite') {
            $dbDatabase = getenv('DB_DATABASE') ?: 'storage/data/test.sqlite';
        }

        $envVars = "DB_CONNECTION={$dbConnection} DB_HOST={$dbHost} DB_PORT={$dbPort} DB_DATABASE={$dbDatabase} DB_USERNAME={$dbUsername} DB_PASSWORD={$dbPassword}";

        $command = "{$envVars} php -S 127.0.0.1:8092 public/index.php > tests/Integration/Http/server_compliance.log 2>&1 & echo $!";
        $dbConn = getenv('DB_CONNECTION') ?: 'pgsql';
        $dbDb = getenv('DB_DATABASE') ?: '';
        $dbHost = getenv('DB_HOST') ?: '';
        $dbUser = getenv('DB_USERNAME') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $command = "DB_CONNECTION={$dbConn} DB_DATABASE={$dbDb} DB_HOST={$dbHost} DB_USERNAME={$dbUser} DB_PASSWORD={$dbPass} php -S 127.0.0.1:8092 public/index.php > tests/Integration/Http/server_compliance.log 2>&1 & echo $!";
        
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);
        
        // Wait for server to bind
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
        Capsule::table('compliance_ledgers')->delete();
        Capsule::table('ledger_entries')->delete();
        Capsule::table('users')->delete();
        Capsule::table('user_roles')->delete();
        Capsule::table('tenants')->whereNotIn('id', ['test-tenant', 'system'])->delete();
        Capsule::table('catalog_variants')->delete();
        Capsule::table('catalog_products')->delete();
        Capsule::table('locations')->where('id', '!=', 'LOC-INT')->delete();

        $suffix = bin2hex(random_bytes(4));
        $this->tenantId = 'tenant-' . $suffix;
        $this->email = 'admin-' . $suffix . '@example.com';
        $this->password = 'SecurePassword123';

        // Setup organization
        $setupRes = $this->request('POST', '/api/setup', [
            'orgName'       => 'Test Org ' . $suffix,
            'tenantId'      => $this->tenantId,
            'adminName'     => 'Admin User',
            'adminEmail'    => $this->email,
            'adminPassword' => $this->password,
        ]);
        $this->assertEquals(200, $setupRes['status']);

        // Login
        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => $this->email,
            'password'  => $this->password,
        $this->token = $loginRes['body']['token'];
    }

    public function testComplianceLedgerLoggingAndVerification(): void
    {
        // 1. Check ledger is empty
        $ledgerRes = $this->request('GET', '/api/compliance/ledger?tenantId=' . $this->tenantId, [], $this->token);
        $this->assertEquals(200, $ledgerRes['status']);
        $this->assertCount(0, $ledgerRes['body']);

        // 2. Perform a stock transaction
        // Setup catalog product and variant first
        $prodRes = $this->request('POST', '/api/catalog/products', [
            'name'        => 'Test Product',
            'description' => 'Test Desc',
            'department'  => 'Test Dept'
        ], $this->token);
        $this->assertEquals(201, $prodRes['status']);
        $productId = $prodRes['body']['id'];

        $varRes = $this->request('POST', "/api/catalog/products/{$productId}/variants", [
            'sku'   => 'SKU-COMP-1',
            'price' => 1000,
            'attributes' => []
        $this->assertTrue(in_array($varRes['status'], [200, 201]));

        // Setup inventory product manually because queue workers might not run in this E2E env
        Capsule::table('products')->insertOrIgnore([
            'id' => bin2hex(random_bytes(16)),
            'sku' => 'SKU-COMP-1',
            'name' => 'Test Product',
            'department' => 'Test Dept',
            'version_id' => 1
        $this->assertEquals(201, $varRes['status']);

        // Setup location
        Capsule::table('locations')->insertOrIgnore([
            'id'   => 'LOC-COMP-1',
            'name' => 'LOC-COMP-1',
            'type' => 'WAREHOUSE'
            'type' => 'WAREHOUSE',

        // Receive stock
        $receiveRes = $this->request('POST', '/api/inventory/receive', [
            'sku'         => 'SKU-COMP-1',
            'quantity'    => 50,
            'location_id' => 'LOC-COMP-1'
        $this->assertEquals(200, $receiveRes['status'], json_encode($receiveRes));

        // 3. Verify ledger entry was created
        $ledgerRes2 = $this->request('GET', '/api/compliance/ledger?tenantId=' . $this->tenantId, [], $this->token);
        $this->assertEquals(200, $ledgerRes2['status']);
        $this->assertCount(1, $ledgerRes2['body']);

        $entry = $ledgerRes2['body'][0];
        $this->assertEquals($this->tenantId, $entry['tenantId']);
        $this->assertEquals('STOCK_ADJUSTED', $entry['eventType']);
        $this->assertEquals(1, $entry['sequenceNumber']);
        $this->assertNotEmpty($entry['currentHash']);
        $this->assertNotEmpty($entry['signature']);

        // 4. Run ledger validation
        $verifyRes = $this->request('POST', '/api/compliance/verify?tenantId=' . $this->tenantId, [], $this->token);
        $this->assertEquals(200, $verifyRes['status']);
        $this->assertTrue($verifyRes['body']['isValid']);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8092' . $path;
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
        $dbPath = $baseDir . '/storage/data/test_compliancee2etest.sqlite';
        if (!file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            @touch($dbPath);
        }
        $extDir = 'C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext';
        $phpExec = PHP_BINARY . ' -d extension_dir="C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" -d extension=pdo -d extension=mbstring -d extension=pdo_sqlite';
        $cmd = $phpExec . ' -S 127.0.0.1:8094 public/index.php';
        
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", __DIR__ . '/server_compliancee2etest.log', "a"],
            2 => ["file", __DIR__ . '/server_compliancee2etest.log', "a"],
        
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


        $this->assertEquals(201, $varRes['status']);

            'type' => 'WAREHOUSE',





    }

    {
        $url = 'http://127.0.0.1:8094' . $path;

        }

        
        }

    }
}
