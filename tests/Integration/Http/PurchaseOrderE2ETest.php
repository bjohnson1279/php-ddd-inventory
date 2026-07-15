<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class PurchaseOrderE2ETest extends TestCase
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
        $dbConn = getenv('DB_CONNECTION') ?: 'pgsql';
        $dbDb = getenv('DB_DATABASE') ?: '';
        $dbHost = getenv('DB_HOST') ?: '';
        $dbUser = getenv('DB_USERNAME') ?: '';
        $dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
        $command = "DB_CONNECTION={$dbConn} DB_DATABASE={$dbDb} DB_HOST={$dbHost} DB_USERNAME={$dbUser} DB_PASSWORD={$dbPass} php -S 127.0.0.1:8086 public/index.php > tests/Integration/Http/server_po.log 2>&1 & echo $!";
        
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
        Capsule::table('purchase_orders')->delete();
        Capsule::table('purchase_order_items')->delete();
        Capsule::table('inventory_cost_layers')->delete();
        Capsule::table('products')->delete();
        Capsule::table('product_locations')->delete();
        Capsule::table('inventory_transactions')->delete();
        Capsule::table('users')->delete();
        Capsule::table('user_roles')->delete();
        Capsule::table('tenants')->where('id', '!=', 'test-tenant')->delete();
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

    public function testPurchaseOrderLifecycle(): void
    {
        // 1. Create a Draft Purchase Order
        $poNumber = 'PO-' . bin2hex(random_bytes(4));
        $createRes = $this->request('POST', '/api/purchase-orders', [
            'purchaseOrderNumber' => $poNumber,
            'vendorId'            => 'VENDOR-123',
            'tenantId'            => $this->tenantId,
            'locationId'          => 'LOC-INT',
            'items'               => [
                [
                    'variantId'     => 'CAT-SKU-1',
                    'quantity'      => 50,
                    'unitCostCents' => 1000
                ]
            ]
        ], $this->token);

        $this->assertEquals(201, $createRes['status'], json_encode($createRes));
        $poId = $createRes['body']['id'];
        $this->assertEquals('DRAFT', $createRes['body']['status']);

        // 2. Get the Purchase Order
        $getRes = $this->request('GET', "/api/purchase-orders/{$poId}", [], $this->token);
        $this->assertEquals(200, $getRes['status'], json_encode($getRes));
        $this->assertEquals('DRAFT', $getRes['body']['status']);
        $this->assertCount(1, $getRes['body']['items']);
        $this->assertEquals('CAT-SKU-1', $getRes['body']['items'][0]['variantId']);
        $this->assertEquals(50, $getRes['body']['items'][0]['quantity']);
        $this->assertEquals(0, $getRes['body']['items'][0]['receivedQuantity']);

        // 3. Approve the Purchase Order
        $approveRes = $this->request('POST', "/api/purchase-orders/{$poId}/approve", [], $this->token);
        $this->assertEquals(200, $approveRes['status'], json_encode($approveRes));

        $getRes = $this->request('GET', "/api/purchase-orders/{$poId}", [], $this->token);
        $this->assertEquals('APPROVED', $getRes['body']['status']);

        // 4. Send the Purchase Order
        $sendRes = $this->request('POST', "/api/purchase-orders/{$poId}/send", [], $this->token);
        $this->assertEquals(200, $sendRes['status'], json_encode($sendRes));

        $getRes = $this->request('GET', "/api/purchase-orders/{$poId}", [], $this->token);
        $this->assertEquals('SENT', $getRes['body']['status']);

        // 5. Receive partially (20 items)
        $receiveRes1 = $this->request('POST', "/api/purchase-orders/{$poId}/receive", [
            'items' => [
                [
                    'variantId'        => 'CAT-SKU-1',
                    'quantityReceived' => 20
                ]
            ]
        ], $this->token);
        $this->assertEquals(200, $receiveRes1['status'], json_encode($receiveRes1));

        $getRes = $this->request('GET', "/api/purchase-orders/{$poId}", [], $this->token);
        $this->assertEquals('PARTIALLY_RECEIVED', $getRes['body']['status']);
        $this->assertEquals(20, $getRes['body']['items'][0]['receivedQuantity']);

        // Assert stock has increased in product_locations table
        $stockLevel = Capsule::table('product_locations')
            ->where('location_id', 'LOC-INT')
            ->first();
        $this->assertNotNull($stockLevel);
        // The SKU corresponds to products in the DB, so we look up by sku
        $prod = Capsule::table('products')->where('sku', 'CAT-SKU-1')->where('tenant_id', $this->tenantId)->first();
        $this->assertNotNull($prod);
        $stockLevel = Capsule::table('product_locations')
            ->where('product_id', $prod->id)
            ->where('location_id', 'LOC-INT')
            ->first();
        $this->assertEquals(20, $stockLevel->stock_quantity);

        // Assert cost layer was created
        $costLayer = Capsule::table('inventory_cost_layers')
            ->where('tenant_id', $this->tenantId)
            ->where('variant_id', 'CAT-SKU-1')
            ->where('purchase_order_id', $poId)
            ->first();
        $this->assertNotNull($costLayer);
        $this->assertEquals(20, $costLayer->original_quantity);
        $this->assertEquals(1000, $costLayer->unit_cost_cents);
        $this->assertEquals($poId, $costLayer->purchase_order_id);

        // 6. Receive remaining (30 items)
        $receiveRes2 = $this->request('POST', "/api/purchase-orders/{$poId}/receive", [
            'items' => [
                [
                    'variantId'        => 'CAT-SKU-1',
                    'quantityReceived' => 30
                ]
            ]
        ], $this->token);
        $this->assertEquals(200, $receiveRes2['status'], json_encode($receiveRes2));

        $getRes = $this->request('GET', "/api/purchase-orders/{$poId}", [], $this->token);
        $this->assertEquals('RECEIVED', $getRes['body']['status']);
        $this->assertEquals(50, $getRes['body']['items'][0]['receivedQuantity']);

        // Assert stock has increased to 50
        $stockLevel = Capsule::table('product_locations')
            ->where('product_id', $prod->id)
            ->where('location_id', 'LOC-INT')
            ->first();
        $this->assertEquals(50, $stockLevel->stock_quantity);

        // Assert second cost layer was created
        $costLayers = Capsule::table('inventory_cost_layers')
            ->where('tenant_id', $this->tenantId)
            ->where('variant_id', 'CAT-SKU-1')
            ->where('purchase_order_id', $poId)
            ->get();
        $this->assertCount(2, $costLayers);
        $quantities = $costLayers->pluck('original_quantity')->all();
        sort($quantities);
        $this->assertEquals([20, 30], $quantities);
    }

    public function testPurchaseOrderRbacPermissions(): void
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

        // Assign staff role explicitly to ensure clean RBAC state
        Capsule::table('user_roles')->where('user_id', $viewerUserId)->delete();
        Capsule::table('user_roles')->insert([
            'user_id' => $viewerUserId,
            'role_id' => 'staff'
        ]);

        // 3. Try to create PO as staff -> should fail (403)
        $poNumber = 'PO-' . bin2hex(random_bytes(4));
        $createRes = $this->request('POST', '/api/purchase-orders', [
            'purchaseOrderNumber' => $poNumber,
            'vendorId'            => 'VENDOR-123',
            'tenantId'            => $this->tenantId,
            'locationId'          => 'LOC-INT',
            'items'               => [
                [
                    'variantId'     => 'CAT-SKU-1',
                    'quantity'      => 50,
                    'unitCostCents' => 1000
                ]
            ]
        ], $staffToken);
        $this->assertEquals(403, $createRes['status']);

        // Admin creates it
        $adminCreateRes = $this->request('POST', '/api/purchase-orders', [
            'purchaseOrderNumber' => $poNumber,
            'vendorId'            => 'VENDOR-123',
            'tenantId'            => $this->tenantId,
            'locationId'          => 'LOC-INT',
            'items'               => [
                [
                    'variantId'     => 'CAT-SKU-1',
                    'quantity'      => 50,
                    'unitCostCents' => 1000
                ]
            ]
        ], $this->token);
        $this->assertEquals(201, $adminCreateRes['status']);
        $poId = $adminCreateRes['body']['id'];

        // 4. Try to approve PO as staff -> should fail (403)
        $approveRes = $this->request('POST', "/api/purchase-orders/{$poId}/approve", [], $staffToken);
        $this->assertEquals(403, $approveRes['status']);

        // 5. Try to get PO as staff -> should succeed (200) since staff has read permission
        $getRes = $this->request('GET', "/api/purchase-orders/{$poId}", [], $staffToken);
        $this->assertEquals(200, $getRes['status']);
        $this->assertEquals('DRAFT', $getRes['body']['status']);
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
