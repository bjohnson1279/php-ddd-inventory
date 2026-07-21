<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class AuditE2ETest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        // Configure mock env variables for the test server
        putenv("SHOPIFY_SHOP_URL=mock.myshopify.com");
        putenv("SHOPIFY_ACCESS_TOKEN=mock-token");
        putenv("QUICKBOOKS_ACCESS_TOKEN=mock-qbo-token");

        $output = [];
        $command = "php -S 127.0.0.1:8092 public/index.php > tests/Integration/Http/server_audit.log 2>&1 & echo $!";
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
        Capsule::table('audit_discrepancies')->delete();
        Capsule::table('shopify_sku_mappings')->delete();
        Capsule::table('shopify_location_mappings')->delete();
        Capsule::table('quickbooks_journal_mappings')->delete();
        Capsule::table('products')->delete();
        Capsule::table('ledger_entries')->delete();
        Capsule::table('journal_entries')->delete();

        Capsule::table('locations')->insertOrIgnore([
            'id' => 'LOC-STOREFRONT',
            'name' => 'Storefront',
            'type' => 'STOREFRONT'
        ]);

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
        $this->assertEquals(200, $setupRes['status'], json_encode($setupRes));

        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => $this->email,
            'password'  => $this->password,
        $this->assertEquals(200, $loginRes['status']);
        $this->token = $loginRes['body']['token'];

        // Seed catalog product and variant for FK constraints
        $catalogProductId = uuidv4();
        Capsule::table('catalog_products')->insert([
            'id' => $catalogProductId,
            'name' => 'iPhone 15 Catalog',
            'description' => 'Test Description',
            'department' => 'Electronics'

        $catalogVariantId = uuidv4();
        Capsule::table('catalog_variants')->insert([
            'id' => $catalogVariantId,
            'product_id' => $catalogProductId,
            'sku' => 'SKU-DIFF',
            'attributes' => '{}',
            'price' => 999.00

        // Seed product with a valid UUID
        $productId = uuidv4();
        Capsule::table('products')->insert([
            'id' => $productId,
            'sku' => 'SKU-DIFF', // ends with -DIFF to mock Shopify mismatch
            'name' => 'iPhone 15',
            'department' => 'Electronics',
            'reorder_threshold' => 10,
            'version_id' => 1

        // Seed shopify mappings
        Capsule::table('shopify_sku_mappings')->insert([
            'id' => uuidv4(),
            'shopify_inventory_item_id' => 'inv-item-123'

        // Ensure 'default' location exists
            'id' => 'default',
            'name' => 'Default Location',
            'type' => 'warehouse'

        Capsule::table('shopify_location_mappings')->insert([
            'our_location_id' => 'LOC-STOREFRONT',
            'shopify_location_id' => 'gid://shopify/Location/12345'

        // Seed ledger entry with quantity
        Capsule::table('ledger_entries')->insert([
            'variant_id' => $productId,
            'quantity' => 10,
            'reason' => 'opening_balance',
            'actor_id' => 'system',
            'metadata' => json_encode(['locationId' => 'default']),
            'occurred_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')

        // Seed journal entry without mapping
        Capsule::table('journal_entries')->insert([
            'entry_date' => date('Y-m-d'),
            'description' => 'Test unmapped journal',
            'method' => 'accrual',
            'lines' => '[]',
    }

    public function testAuditLifecycle(): void
    {
        // 1. Run audit
        $runRes = $this->request('POST', '/api/audit/run', [], $this->token);
        $this->assertEquals(200, $runRes['status'], json_encode($runRes));
        $this->assertEquals(1, $runRes['body']['shopifyDiscrepancies']);
        $this->assertEquals(1, $runRes['body']['accountingDiscrepancies']);

        // 2. List open discrepancies
        $listRes = $this->request('GET', '/api/audit/discrepancies?status=OPEN', [], $this->token);
        $this->assertEquals(200, $listRes['status']);
        $this->assertCount(2, $listRes['body']['discrepancies']);

        $shopifyDisc = null;
        foreach ($listRes['body']['discrepancies'] as $disc) {
            if ($disc['type'] === 'SHOPIFY_STOCK_MISMATCH') {
                $shopifyDisc = $disc;
            }
        }
        $this->assertNotNull($shopifyDisc);

        // 3. Resolve the Shopify discrepancy
        $resolveRes = $this->request('POST', '/api/audit/discrepancies/' . $shopifyDisc['id'] . '/resolve', [
            'notes' => 'Manually synchronized stock levels'
        ], $this->token);
        $this->assertEquals(200, $resolveRes['status']);
        $this->assertTrue($resolveRes['body']['success']);

        // 4. Verify status is updated to RESOLVED
        $verifyRes = $this->request('GET', '/api/audit/discrepancies?status=RESOLVED', [], $this->token);
        $this->assertEquals(200, $verifyRes['status']);
        $this->assertCount(1, $verifyRes['body']['discrepancies']);
        $this->assertEquals($shopifyDisc['id'], $verifyRes['body']['discrepancies'][0]['id']);
        $this->assertEquals('Manually synchronized stock levels', $verifyRes['body']['discrepancies'][0]['resolutionNotes']);
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

    {

        $command = "php -S 127.0.0.1:8094 public/index.php > tests/Integration/Http/server_audit.log 2>&1 & echo $!";
        
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
        }


    }

    {
        $url = 'http://127.0.0.1:8094' . $path;

        }

        
        }

    }
}





{
    private static $serverProcess = null;

        public static function setUpBeforeClass(): void
    {
        $baseDir = realpath(__DIR__ . '/../../..');
        $dbPath = $baseDir . '/storage/data/test_audite2etest.sqlite';
        if (!file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            @touch($dbPath);
        }
        $extDir = 'C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext';
        $phpExec = PHP_BINARY . ' -d extension_dir="C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" -d extension=pdo -d extension=mbstring -d extension=pdo_sqlite';
        $cmd = $phpExec . ' -S 127.0.0.1:8095 public/index.php';
        
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", __DIR__ . '/server_audite2etest.log', "a"],
            2 => ["file", __DIR__ . '/server_audite2etest.log', "a"],
        
        putenv('SHOPIFY_SHOP_URL=mock-store.myshopify.com');
        putenv('SHOPIFY_ACCESS_TOKEN=mock-token');
        putenv('QUICKBOOKS_ACCESS_TOKEN=mock-token');
        $_ENV['SHOPIFY_SHOP_URL'] = 'mock-store.myshopify.com';
        $_ENV['SHOPIFY_ACCESS_TOKEN'] = 'mock-token';
        $_ENV['QUICKBOOKS_ACCESS_TOKEN'] = 'mock-token';

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
        Capsule::table('catalog_variants')->delete();
        Capsule::table('catalog_products')->delete();
                Capsule::table('audit_discrepancies')->delete();












    }

    {


            }
        }


    }

    {
        $url = 'http://127.0.0.1:8095' . $path;

        }

        
        }

    }
}
