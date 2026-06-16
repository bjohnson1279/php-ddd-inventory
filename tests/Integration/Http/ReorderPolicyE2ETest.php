<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ReorderPolicyE2ETest extends TestCase
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
        $command = "php -S 127.0.0.1:8086 public/index.php > tests/Integration/Http/server.log 2>&1 & echo $!";
        
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);
        
        // Wait for server to bind
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8086, $errno, $errstr, 0.1);
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
        Capsule::table('reorder_policies')->delete();
        Capsule::table('purchase_orders')->delete();
        Capsule::table('purchase_order_items')->delete();
        Capsule::table('products')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('inventory_transactions')->delete();

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

        // Seed product
        Capsule::table('products')->insert([
            'id' => uuidv4(),
            'tenant_id' => $this->tenantId,
            'sku' => 'CAT-SKU-1',
            'name' => 'Test Product',
            'department' => 'Test Department',
            'reorder_threshold' => 10,
            'version_id' => 1
        ]);
    }

    public function testReorderPolicyLifecycle(): void
    {
        // 1. Create a Reorder Policy
        $createRes = $this->request('POST', '/api/reorder-policies', [
            'sku'             => 'CAT-SKU-1',
            'locationId'      => 'LOC-INT',
            'reorderPoint'    => 10,
            'reorderQuantity' => 50,
            'safetyStock'     => 5
        ], $this->token);

        $this->assertEquals(200, $createRes['status'], json_encode($createRes));
        $this->assertEquals('CAT-SKU-1', $createRes['body']['sku']);
        $this->assertEquals(10, $createRes['body']['reorderPoint']);
        $this->assertEquals(50, $createRes['body']['reorderQuantity']);

        // 2. Get the Reorder Policy
        $getRes = $this->request('GET', '/api/reorder-policies/CAT-SKU-1/LOC-INT', [], $this->token);
        $this->assertEquals(200, $getRes['status'], json_encode($getRes));
        $this->assertEquals(10, $getRes['body']['reorderPoint']);

        // 3. Receive stock to start at 20 (above reorder point of 10)
        $receiveRes = $this->request('POST', '/api/inventory/receive', [
            'sku'         => 'CAT-SKU-1',
            'quantity'    => 20,
            'location_id' => 'LOC-INT'
        ], $this->token);
        $this->assertEquals(200, $receiveRes['status'], json_encode($receiveRes));

        // 4. Dispatch stock (5 items) -> stock becomes 15 (still > 10) -> no auto-reorder PO created
        $dispatchRes1 = $this->request('POST', '/api/inventory/dispatch', [
            'sku'         => 'CAT-SKU-1',
            'quantity'    => 5,
            'location_id' => 'LOC-INT'
        ], $this->token);
        $this->assertEquals(200, $dispatchRes1['status'], json_encode($dispatchRes1));

        $draftPoCount = Capsule::table('purchase_orders')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'DRAFT')
            ->count();
        $this->assertEquals(0, $draftPoCount);

        // 5. Dispatch stock (6 items) -> stock becomes 9 (<= 10) -> auto-reorder PO should be created!
        $dispatchRes2 = $this->request('POST', '/api/inventory/dispatch', [
            'sku'         => 'CAT-SKU-1',
            'quantity'    => 6,
            'location_id' => 'LOC-INT'
        ], $this->token);
        $this->assertEquals(200, $dispatchRes2['status'], json_encode($dispatchRes2));

        $draftPo = Capsule::table('purchase_orders')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'DRAFT')
            ->first();
        $this->assertNotNull($draftPo, "Draft purchase order should have been auto-generated");
        $this->assertEquals('AUTO-SYSTEM-VENDOR', $draftPo->vendor_id);
        $this->assertStringStartsWith('AUTO-REORDER-CAT-SKU-1-', $draftPo->purchase_order_number);

        // Verify the item
        $poItem = Capsule::table('purchase_order_items')
            ->where('purchase_order_id', $draftPo->id)
            ->first();
        $this->assertNotNull($poItem);
        $this->assertEquals('CAT-SKU-1', $poItem->variant_id);
        $this->assertEquals(50, $poItem->quantity); // Reorder quantity
    }

    public function testReorderPolicyRbacPermissions(): void
    {
        $suffix = bin2hex(random_bytes(4));
        // 1. Invite new user
        $inviteRes = $this->request('POST', '/api/users', [
            'email' => "staff-{$suffix}@example.com",
        ], $this->token);
        
        $this->assertEquals(201, $inviteRes['status'], json_encode($inviteRes));
        $viewerUserId = $inviteRes['body']['user_id'];
        $tempPassword = $inviteRes['body']['temporary_password'];

        // 2. Login as staff
        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => "staff-{$suffix}@example.com",
            'password'  => $tempPassword,
        ]);
        $this->assertEquals(200, $loginRes['status'], json_encode($loginRes));
        $staffToken = $loginRes['body']['token'];

        // Assign staff role explicitly
        Capsule::table('user_roles')->where('user_id', $viewerUserId)->delete();
        Capsule::table('user_roles')->insert([
            'user_id' => $viewerUserId,
            'role_id' => 'staff'
        ]);

        // 3. Try to create policy as staff -> should fail (403)
        $createRes = $this->request('POST', '/api/reorder-policies', [
            'sku'             => 'CAT-SKU-1',
            'locationId'      => 'LOC-INT',
            'reorderPoint'    => 10,
            'reorderQuantity' => 50,
            'safetyStock'     => 5
        ], $staffToken);
        $this->assertEquals(403, $createRes['status']);

        // Admin creates it
        $adminCreateRes = $this->request('POST', '/api/reorder-policies', [
            'sku'             => 'CAT-SKU-1',
            'locationId'      => 'LOC-INT',
            'reorderPoint'    => 10,
            'reorderQuantity' => 50,
            'safetyStock'     => 5
        ], $this->token);
        $this->assertEquals(200, $adminCreateRes['status']);

        // 4. Try to get policy as staff -> should succeed (200) since staff has read permission
        $getRes = $this->request('GET', '/api/reorder-policies/CAT-SKU-1/LOC-INT', [], $staffToken);
        $this->assertEquals(200, $getRes['status']);
        $this->assertEquals(10, $getRes['body']['reorderPoint']);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8086' . $path;
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
