<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class AllocationsE2ETest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        // Start built-in PHP development server in the background on port 8087
        $output = [];
        $command = "php -S 127.0.0.1:8087 public/index.php > tests/Integration/Http/server_allocations.log 2>&1 & echo $!";
        
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);
        
        // Wait for server to bind
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8087, $errno, $errstr, 0.1);
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
        Capsule::table('product_locations')->delete();
        Capsule::table('products')->delete();
        Capsule::table('user_roles')->delete();
        Capsule::table('users')->delete();

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
        ]);

        $this->assertEquals(200, $loginRes['status'], json_encode($loginRes));
        $this->token = $loginRes['body']['token'];
    }

    public function testRbacRoleConstraints(): void
    {
        $suffix = bin2hex(random_bytes(4));
        // 1. Invite new user (default staff/viewer role)
        $inviteRes = $this->request('POST', '/api/users', [
            'email' => "viewer-{$suffix}@example.com",
        ], $this->token);
        
        $this->assertEquals(201, $inviteRes['status'], json_encode($inviteRes));
        $viewerUserId = $inviteRes['body']['user_id'];
        $tempPassword = $inviteRes['body']['temporary_password'];

        // 2. Login as viewer
        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => "viewer-{$suffix}@example.com",
            'password'  => $tempPassword,
        ]);
        $this->assertEquals(200, $loginRes['status'], json_encode($loginRes));
        $viewerToken = $loginRes['body']['token'];

        // 3. Strip all roles from viewer so they have no permissions
        Capsule::table('user_roles')->where('user_id', $viewerUserId)->delete();

        // 4. Try allocate stock (mutating), should get 403
        $allocRes = $this->request('POST', '/api/inventory/allocate', [
            'sku' => 'TEST-SKU',
            'amount' => 5,
            'location_id' => 'LOC-INT'
        ], $viewerToken);
        $this->assertEquals(403, $allocRes['status']);
    }

    public function testAllocationEndpointsFlow(): void
    {
        $skuStr = 'TEST-SKU-ALLOC';
        $locationId = 'LOC-INT';

        // 1. Setup product catalog
        $createProdRes = $this->request('POST', '/api/catalog/products', [
            'id' => uuidv4(),
            'sku' => $skuStr,
            'name' => 'Test Allocation Product',
            'department' => 'electronics',
            'initialLocation' => $locationId,
            'initialStock' => 20,
        ], $this->token);
        $this->assertEquals(201, $createProdRes['status'], json_encode($createProdRes));

        // 2. Allocate 8 units
        $allocRes = $this->request('POST', '/api/inventory/allocate', [
            'sku' => $skuStr,
            'amount' => 8,
            'location_id' => $locationId,
        ], $this->token);
        $this->assertEquals(200, $allocRes['status'], json_encode($allocRes));

        // 3. Verify counts via GET /api/inventory/{sku}
        $getRes1 = $this->request('GET', "/api/inventory/{$skuStr}?location_id={$locationId}", [], $this->token);
        $this->assertEquals(200, $getRes1['status']);
        $this->assertEquals(20, $getRes1['body']['quantity']);
        $this->assertEquals(8, $getRes1['body']['allocated']);
        $this->assertEquals(12, $getRes1['body']['available']);

        // 4. Release 3 units of allocation
        $releaseRes = $this->request('POST', '/api/inventory/release-allocation', [
            'sku' => $skuStr,
            'amount' => 3,
            'location_id' => $locationId,
        ], $this->token);
        $this->assertEquals(200, $releaseRes['status'], json_encode($releaseRes));

        $getRes2 = $this->request('GET', "/api/inventory/{$skuStr}?location_id={$locationId}", [], $this->token);
        $this->assertEquals(5, $getRes2['body']['allocated']);
        $this->assertEquals(15, $getRes2['body']['available']);

        // 5. Fulfill 5 units of allocation (decreases both quantity and allocation)
        $fulfillRes = $this->request('POST', '/api/inventory/fulfill-allocation', [
            'sku' => $skuStr,
            'amount' => 5,
            'location_id' => $locationId,
        ], $this->token);
        $this->assertEquals(200, $fulfillRes['status'], json_encode($fulfillRes));

        $getRes3 = $this->request('GET', "/api/inventory/{$skuStr}?location_id={$locationId}", [], $this->token);
        $this->assertEquals(15, $getRes3['body']['quantity']);
        $this->assertEquals(0, $getRes3['body']['allocated']);
        $this->assertEquals(15, $getRes3['body']['available']);

        // 6. Requesting more than available stock should return 400 with InsufficientAvailableStockException
        $allocOverRes = $this->request('POST', '/api/inventory/allocate', [
            'sku' => $skuStr,
            'amount' => 16, // available is 15
            'location_id' => $locationId,
        ], $this->token);
        $this->assertEquals(400, $allocOverRes['status']);
        $this->assertEquals('InsufficientAvailableStockException', $allocOverRes['body']['type'] ?? null);
    }

    public function testInTransitEndpointsFlow(): void
    {
        $skuStr = 'TEST-SKU-TRANSIT';
        $locationId = 'LOC-INT';

        // 1. Setup product catalog
        $createProdRes = $this->request('POST', '/api/catalog/products', [
            'id' => uuidv4(),
            'sku' => $skuStr,
            'name' => 'Test Transit Product',
            'department' => 'electronics',
            'initialLocation' => $locationId,
            'initialStock' => 10,
        ], $this->token);
        $this->assertEquals(201, $createProdRes['status'], json_encode($createProdRes));

        // 2. Create in-transit stock of 10
        $transitRes = $this->request('POST', '/api/inventory/create-in-transit', [
            'sku' => $skuStr,
            'amount' => 10,
            'location_id' => $locationId,
        ], $this->token);
        $this->assertEquals(200, $transitRes['status'], json_encode($transitRes));

        $getRes1 = $this->request('GET', "/api/inventory/{$skuStr}?location_id={$locationId}", [], $this->token);
        $this->assertEquals(10, $getRes1['body']['inTransit']);
        $this->assertEquals(20, $getRes1['body']['available']);

        // 3. Receive 6 units from in-transit (increases quantity, decreases inTransit)
        $receiveRes = $this->request('POST', '/api/inventory/receive-in-transit', [
            'sku' => $skuStr,
            'amount' => 6,
            'location_id' => $locationId,
        ], $this->token);
        $this->assertEquals(200, $receiveRes['status'], json_encode($receiveRes));

        $getRes2 = $this->request('GET', "/api/inventory/{$skuStr}?location_id={$locationId}", [], $this->token);
        $this->assertEquals(16, $getRes2['body']['quantity']);
        $this->assertEquals(4, $getRes2['body']['inTransit']);
        $this->assertEquals(20, $getRes2['body']['available']);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8087' . $path;
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
        ];
    }
}
