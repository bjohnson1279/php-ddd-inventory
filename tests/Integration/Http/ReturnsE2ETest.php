<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ReturnsE2ETest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        // Start built-in PHP development server in the background on port 8086
        $output = [];
        $command = "php -S 127.0.0.1:8090 public/index.php > tests/Integration/Http/server_returns.log 2>&1 & echo $!";

        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);

        // Wait for server to bind
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8090, $errno, $errstr, 0.1);
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
        // Generate unique tenant details for each test run to ensure isolation
        Capsule::table('users')->delete();
        Capsule::table('user_roles')->delete();
        Capsule::table('tenants')->where('id', '!=', 'test-tenant')->delete();
        Capsule::table('tenants')->whereNotIn('id', ['test-tenant', 'system'])->delete();
        \Illuminate\Database\Capsule\Manager::table('tenants')->insertOrIgnore([['id' => 'test-tenant', 'name' => 'Test Tenant']]);
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

        $this->assertEquals(200, $setupRes['status'], json_encode($setupRes));

        // 2. Login to get token
        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => $this->email,
            'password'  => $this->password,

        $this->assertEquals(200, $loginRes['status'], json_encode($loginRes));
        $this->assertNotEmpty($loginRes['body']['token']);
        $this->token = $loginRes['body']['token'];
    }

    public function testReturnsRbacPermissions(): void
    {
        $suffix = bin2hex(random_bytes(4));
        // 1. Invite new user
        $inviteRes = $this->request('POST', '/api/users', [
            'email' => "viewer-{$suffix}@example.com",
        ], $this->token);

        $this->assertEquals(201, $inviteRes['status'], json_encode($inviteRes));
        $viewerUserId = $inviteRes['body']['user_id'];
        $tempPassword = $inviteRes['body']['temporary_password'];

        // 2. Login as viewer
            'email'     => "viewer-{$suffix}@example.com",
            'password'  => $tempPassword,
        $viewerToken = $loginRes['body']['token'];

        // 3. Strip all roles from viewer so they have no permissions
        Capsule::table('user_roles')->where('user_id', $viewerUserId)->delete();

        // 4. Try mutating operations, should get 403
        $createRes = $this->request('POST', '/api/returns/rma', [
            'rmaNumber' => "RMA-{$suffix}",
            'customerId' => 'CUST-1',
            'locationId' => 'LOC-INT',
            'items' => [['variantId' => 'VAR-A', 'quantity' => 1, 'unitCostCents' => 1000]]
        ], $viewerToken);
        $this->assertEquals(403, $createRes['status']);

        $authRes = $this->request('POST', "/api/returns/rma/some-id/authorize", [], $viewerToken);
        $this->assertEquals(403, $authRes['status']);

        $receiveRes = $this->request('POST', "/api/returns/rma/some-id/receive", [
            'items' => [['variantId' => 'VAR-A', 'quantityReceived' => 1, 'disposition' => 'RESTOCK']]
        $this->assertEquals(403, $receiveRes['status']);

        $resolveRes = $this->request('POST', "/api/returns/quarantine/some-id/resolve", [
            'resolution' => 'RESTOCK'
        $this->assertEquals(403, $resolveRes['status']);

        // 5. Try reading operations, should be allowed (will get 404 or 200, but not 403)
        $readRmaRes = $this->request('GET', "/api/returns/rma/some-id", [], $viewerToken);
        $this->assertEquals(404, $readRmaRes['status']);

        $readQuarantineRes = $this->request('GET', "/api/returns/quarantine", [], $viewerToken);
        $this->assertEquals(200, $readQuarantineRes['status']);
    }

    public function testCompleteReturnsAndQuarantineLifecycle(): void
    {
        $varX = uuidv4();
        $varY = uuidv4();
        $skuX = "VAR-X-{$suffix}";
        $skuY = "VAR-Y-{$suffix}";
        $serialX = "SN-X-{$suffix}";

        // Seed locations first
        Capsule::table('locations')->insertOrIgnore([
            ['id' => 'LOC-INT-quarantine', 'name' => 'Integration Quarantine Location', 'type' => 'TEST']

        // Seed products and product locations
        Capsule::table('products')->insert([
            [
                'id' => $varX,
                'tenant_id' => $this->tenantId,
                'sku' => $skuX,
                'name' => 'Product X',
                'department' => 'GEN',
                'reorder_threshold' => 10,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
                'id' => $varY,
                'sku' => $skuY,
                'name' => 'Product Y',
            ]

        Capsule::table('product_locations')->insert([
                'product_id' => $varX,
                'location_id' => 'LOC-INT',
                'stock_quantity' => 0,
                'open_box_quantity' => 0,
                'damaged_quantity' => 0,
                'location_id' => 'LOC-INT-quarantine',
                'product_id' => $varY,

        // Register, receive, and sell a serialized item for VAR-X to verify status transitions
        $regSerialRes = $this->request('POST', '/api/serials', [
            'variant_id' => $varX,
            'serial_number' => $serialX,
            'location_id' => 'LOC-INT'
        $this->assertEquals(201, $regSerialRes['status'], json_encode($regSerialRes));
        $serialItemId = $regSerialRes['body']['id'];

        $recvSerialRes = $this->request('POST', "/api/serials/{$serialItemId}/receive", [
            'location_id' => 'LOC-INT',
            'purchase_order_id' => "PO-{$suffix}",
            'unit_cost_cents' => 1500
        $this->assertEquals(200, $recvSerialRes['status'], json_encode($recvSerialRes));

        $sellSerialRes = $this->request('POST', "/api/serials/{$serialItemId}/sell", [
            'sale_id' => "SALE-{$suffix}"
        $this->assertEquals(200, $sellSerialRes['status'], json_encode($sellSerialRes));

        // 1. Create RMA request
        $rmaNumber = "RMA-{$suffix}";
        $createRmaRes = $this->request('POST', '/api/returns/rma', [
            'rmaNumber' => $rmaNumber,
            'customerId' => 'CUST-E2E',
            'items' => [
                ['variantId' => $varX, 'quantity' => 1, 'unitCostCents' => 1500],
                ['variantId' => $varY, 'quantity' => 2, 'unitCostCents' => 2500]

        $this->assertEquals(201, $createRmaRes['status'], json_encode($createRmaRes));
        $rmaId = $createRmaRes['body']['id'];
        $this->assertEquals('REQUESTED', $createRmaRes['body']['status']);
        $this->assertCount(2, $createRmaRes['body']['items']);

        // 2. Authorize RMA
        $authRmaRes = $this->request('POST', "/api/returns/rma/{$rmaId}/authorize", [], $this->token);
        $this->assertEquals(200, $authRmaRes['status'], json_encode($authRmaRes));

        $getRmaRes = $this->request('GET', "/api/returns/rma/{$rmaId}", [], $this->token);
        $this->assertEquals(200, $getRmaRes['status'], json_encode($getRmaRes));
        $this->assertEquals('AUTHORIZED', $getRmaRes['body']['status']);

        // 3. Receive items (VAR-X restocked, VAR-Y quarantined)
        $recvRmaRes = $this->request('POST', "/api/returns/rma/{$rmaId}/receive", [
                [
                    'variantId' => $varX,
                    'quantityReceived' => 1,
                    'disposition' => 'RESTOCK',
                    'serialNumbers' => [$serialX]
                ],
                    'variantId' => $varY,
                    'quantityReceived' => 2,
                    'disposition' => 'QUARANTINE'
                ]
        $this->assertEquals(200, $recvRmaRes['status'], json_encode($recvRmaRes));

        // Verify stock adjustments
        $stockX = (int)Capsule::table('product_locations')
            ->where('product_id', $varX)
            ->where('location_id', 'LOC-INT')
            ->value('stock_quantity');
        $stockYQ = (int)Capsule::table('product_locations')
            ->where('product_id', $varY)
            ->where('location_id', 'LOC-INT-quarantine')

        $this->assertEquals(1, $stockX);
        $this->assertEquals(2, $stockYQ);

        // Verify serialized item status is 'in_stock'
        $lookupSerialRes = $this->request('GET', "/api/serials/lookup?serial_number={$serialX}", [], $this->token);
        $this->assertEquals(200, $lookupSerialRes['status'], json_encode($lookupSerialRes));
        $this->assertEquals('in_stock', $lookupSerialRes['body']['status']);

        // 4. List Quarantine items to find the created Quarantine item
        $listQRes = $this->request('GET', '/api/returns/quarantine', [], $this->token);
        $this->assertEquals(200, $listQRes['status'], json_encode($listQRes));

        $targetQItem = null;
        foreach ($listQRes['body'] as $qItem) {
            if ($qItem['variantId'] === $varY) {
                $targetQItem = $qItem;
            }
        }
        $this->assertNotNull($targetQItem);
        $this->assertEquals(2, $targetQItem['quantity']);
        $this->assertEquals('QUARANTINED', $targetQItem['status']);

        $qItemId = $targetQItem['id'];

        // 5. Resolve Quarantine item as RESTOCK
        $resolveRes = $this->request('POST', "/api/returns/quarantine/{$qItemId}/resolve", [
        $this->assertEquals(200, $resolveRes['status'], json_encode($resolveRes));

        // Verify stock is transferred to standard warehouse
        $stockY = (int)Capsule::table('product_locations')
        $stockYQResolved = (int)Capsule::table('product_locations')

        $this->assertEquals(2, $stockY);
        $this->assertEquals(0, $stockYQResolved);

        // Verify resolved quarantine item details
        $getQRes = $this->request('GET', "/api/returns/quarantine/{$qItemId}", [], $this->token);
        $this->assertEquals(200, $getQRes['status'], json_encode($getQRes));
        $this->assertEquals('RESTOCKED', $getQRes['body']['status']);
        $this->assertNotNull($getQRes['body']['resolvedAt']);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8090' . $path;
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

        return [
            'status' => $statusCode,
            'body'   => json_decode((string)$result, true) ?: $result
    }
}





{
    private static $serverProcess = null;

        public static function setUpBeforeClass(): void
    {
        $baseDir = realpath(__DIR__ . '/../../..');
        $dbPath = $baseDir . '/storage/data/test_returnse2etest.sqlite';
        if (!file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            @touch($dbPath);
        }
        $extDir = 'C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext';
        $phpExec = PHP_BINARY . ' -d extension_dir="C:\Users\johns\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" -d extension=pdo -d extension=mbstring -d extension=pdo_sqlite';
        $cmd = $phpExec . ' -S 127.0.0.1:8091 public/index.php';
        
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["file", __DIR__ . '/server_returnse2etest.log', "a"],
            2 => ["file", __DIR__ . '/server_returnse2etest.log', "a"],
        
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












        


        
            }
        }



        

    }

    {
        $url = 'http://127.0.0.1:8091' . $path;

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
        Capsule::table('tenants')->whereNotIn('id', ['test-tenant', 'system'])->delete();




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
