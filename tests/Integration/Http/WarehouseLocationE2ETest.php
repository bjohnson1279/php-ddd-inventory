<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class WarehouseLocationE2ETest extends TestCase
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
        $env = "DB_CONNECTION={$dbConn} DB_DATABASE={$dbDb} DB_HOST={$dbHost} DB_USERNAME={$dbUser}";
        if ($dbPass !== '') $env .= " DB_PASSWORD={$dbPass}";
        $command = "{$env} php -S 127.0.0.1:8091 public/index.php > tests/Integration/Http/server_warehouse.log 2>&1 & echo $!";
        
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);
        
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
        Capsule::table('warehouse_locations')->delete();
        Capsule::table('users')->delete();
        Capsule::table('user_roles')->delete();
        Capsule::table('tenants')->where('id', '!=', 'test-tenant')->delete();
        \Illuminate\Database\Capsule\Manager::table('tenants')->insertOrIgnore([['id' => 'test-tenant', 'name' => 'Test Tenant']]);
                Capsule::table('catalog_variants')->delete();
        Capsule::table('catalog_products')->delete();
        Capsule::table('locations')->where('id', '!=', 'LOC-INT')->delete();

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

    public function testWarehouseLocationsRbacPermissions(): void
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
        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => "viewer-{$suffix}@example.com",
            'password'  => $tempPassword,
        ]);
        $this->assertEquals(200, $loginRes['status'], json_encode($loginRes));
        $viewerToken = $loginRes['body']['token'];

        // 3. Strip all roles from viewer so they have no permissions
        Capsule::table('user_roles')->where('user_id', $viewerUserId)->delete();

        // 4. Try save location (mutating), should get 403
        $saveRes = $this->request('POST', '/api/warehouse-locations', [
            'path' => 'WH1-ZONEA-A01-R01-S01-B01',
            'maxWeightGrams' => 50000,
            'maxVolumeCubicMeters' => 2.0
        ], $viewerToken);
        $this->assertEquals(403, $saveRes['status']);

        // 5. Try delete location (mutating), should get 403
        $deleteRes = $this->request('DELETE', '/api/warehouse-locations/WH1-ZONEA-A01-R01-S01-B01', [], $viewerToken);
        $this->assertEquals(403, $deleteRes['status']);

        // 6. Admin should successfully save and delete
        $adminSaveRes = $this->request('POST', '/api/warehouse-locations', [
            'path' => 'WH1-ZONEA-A01-R01-S01-B01',
            'maxWeightGrams' => 50000,
            'maxVolumeCubicMeters' => 2.0
        ], $this->token);
        $this->assertEquals(200, $adminSaveRes['status'], json_encode($adminSaveRes));

        $adminListRes = $this->request('GET', '/api/warehouse-locations', [], $viewerToken); // GET list is non-mutating
        $this->assertEquals(200, $adminListRes['status']);
        $this->assertCount(1, $adminListRes['body']);
    }

    public function testWarehouseLocationsCrud(): void
    {
        // Save
        $saveRes = $this->request('POST', '/api/warehouse-locations', [
            'warehouseId' => 'WH1',
            'zone'        => 'ZONEB',
            'aisle'       => 'A02',
            'rack'        => 'R03',
            'shelf'       => 'S04',
            'bin'         => 'B05',
            'maxWeightGrams' => 10000,
            'maxVolumeCubicMeters' => 1.5
        ], $this->token);
        $this->assertEquals(200, $saveRes['status'], json_encode($saveRes));
        $this->assertEquals('WH1-ZONEB-A02-R03-S04-B05', $saveRes['body']['location']['id']);

        // List
        $listRes = $this->request('GET', '/api/warehouse-locations', [], $this->token);
        $this->assertEquals(200, $listRes['status']);
        $this->assertCount(1, $listRes['body']);

        // Delete
        $deleteRes = $this->request('DELETE', '/api/warehouse-locations/WH1-ZONEB-A02-R03-S04-B05', [], $this->token);
        $this->assertEquals(200, $deleteRes['status']);

        // List again (empty)
        $listRes2 = $this->request('GET', '/api/warehouse-locations', [], $this->token);
        $this->assertCount(0, $listRes2['body']);
    }

    public function testWarehouseCapacityConstraints(): void
    {
        // 1. Save warehouse location
        $saveRes = $this->request('POST', '/api/warehouse-locations', [
            'path' => 'WH1-ZONEA-A01-R01-S01-B01',
            'maxWeightGrams' => 10000,
            'maxVolumeCubicMeters' => 2.0
        ], $this->token);
        $this->assertEquals(200, $saveRes['status']);

        Capsule::table('locations')->insertOrIgnore([
            'id'   => 'WH1-ZONEA-A01-R01-S01-B01',
            'name' => 'WH1-ZONEA-A01-R01-S01-B01',
            'type' => 'WAREHOUSE'
        ]);

        // 2. Seed product
        Capsule::table('products')->insert([
            'id' => uuidv4(),
            'tenant_id' => $this->tenantId,
            'sku' => 'TSHIRT-SM-RED',
            'name' => 'Classic Tee',
            'department' => 'Apparel',
            'weight_grams' => 100,
            'volume_cubic_meters' => 0.05,
            'reorder_threshold' => 10,
            'version_id' => 1
        ]);

        // 3. Receive stock that fits capacity
        $receiveRes1 = $this->request('POST', '/api/inventory/receive', [
            'sku' => 'TSHIRT-SM-RED',
            'quantity' => 30,
            'location_id' => 'WH1-ZONEA-A01-R01-S01-B01'
        ], $this->token);
        $this->assertEquals(200, $receiveRes1['status'], json_encode($receiveRes1));

        // 4. Receive stock that exceeds weight limit (150 * 100g = 15000g > 10000g)
        $receiveRes2 = $this->request('POST', '/api/inventory/receive', [
            'sku' => 'TSHIRT-SM-RED',
            'quantity' => 150,
            'location_id' => 'WH1-ZONEA-A01-R01-S01-B01'
        ], $this->token);
        $this->assertEquals(400, $receiveRes2['status']);
        $this->assertStringContainsString('weight limit', $receiveRes2['body']['error']);

        // 5. Receive stock that exceeds volume limit (60 * 0.05 = 3.0 m3 > 2.0 m3)
        $receiveRes3 = $this->request('POST', '/api/inventory/receive', [
            'sku' => 'TSHIRT-SM-RED',
            'quantity' => 60,
            'location_id' => 'WH1-ZONEA-A01-R01-S01-B01'
        ], $this->token);
        $this->assertEquals(400, $receiveRes3['status']);
        $this->assertStringContainsString('volume limit', $receiveRes3['body']['error']);
    }

    public function testWarehousePutawaySuggestions(): void
    {
        // 1. Create locations
        $this->request('POST', '/api/warehouse-locations', ['path' => 'WH1-FAST-A01-R01-S01-B01', 'maxWeightGrams' => 100000, 'maxVolumeCubicMeters' => 10.0], $this->token);
        $this->request('POST', '/api/warehouse-locations', ['path' => 'WH1-HAZMAT-A05-R01-S01-B01', 'maxWeightGrams' => 100000, 'maxVolumeCubicMeters' => 10.0], $this->token);
        $this->request('POST', '/api/warehouse-locations', ['path' => 'WH1-COLD-A02-R01-S01-B01', 'maxWeightGrams' => 100000, 'maxVolumeCubicMeters' => 10.0], $this->token);

        // 2. Seed catalog product and variant
        $productId = uuidv4();
        $variantId = uuidv4();
        Capsule::table('catalog_products')->insert([
            'id' => $productId,
            'name' => 'Fast Product',
            'department' => 'Apparel'
        ]);
        Capsule::table('catalog_variants')->insert([
            'id' => $variantId,
            'product_id' => $productId,
            'sku' => 'FAST-SKU',
            'attributes' => json_encode(['velocity' => 'fast-moving']),
            'price' => 15.00
        ]);
        Capsule::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $this->tenantId,
            'sku' => 'FAST-SKU',
            'name' => 'Fast Product',
            'department' => 'Apparel',
            'weight_grams' => 100,
            'volume_cubic_meters' => 0.01,
            'reorder_threshold' => 10,
            'version_id' => 1
        ]);

        // 3. Request suggestions
        $suggestRes = $this->request('POST', '/api/warehouse-locations/putaway-suggestions', [
            'sku' => 'FAST-SKU',
            'quantity' => 10
        ], $this->token);

        $this->assertEquals(200, $suggestRes['status'], json_encode($suggestRes));
        $this->assertNotEmpty($suggestRes['body']);
        $this->assertEquals('WH1-FAST-A01-R01-S01-B01', $suggestRes['body'][0]['locationId']);
    }

    public function testPickingRouteSerpentineSorting(): void
    {
        // 1. Create locations
        $this->request('POST', '/api/warehouse-locations', ['path' => 'WH1-ZONEA-A01-R01-S01-B01', 'maxWeightGrams' => 100000, 'maxVolumeCubicMeters' => 10.0], $this->token);
        $this->request('POST', '/api/warehouse-locations', ['path' => 'WH1-ZONEA-A01-R02-S01-B01', 'maxWeightGrams' => 100000, 'maxVolumeCubicMeters' => 10.0], $this->token);
        $this->request('POST', '/api/warehouse-locations', ['path' => 'WH1-ZONEA-A02-R01-S01-B01', 'maxWeightGrams' => 100000, 'maxVolumeCubicMeters' => 10.0], $this->token);
        $this->request('POST', '/api/warehouse-locations', ['path' => 'WH1-ZONEA-A02-R02-S01-B01', 'maxWeightGrams' => 100000, 'maxVolumeCubicMeters' => 10.0], $this->token);

        // 2. Request picking route optimization
        $items = [
            ['sku' => 'SKU1', 'quantity' => 2, 'locationId' => 'WH1-ZONEA-A02-R01-S01-B01'],
            ['sku' => 'SKU2', 'quantity' => 1, 'locationId' => 'WH1-ZONEA-A01-R02-S01-B01'],
            ['sku' => 'SKU3', 'quantity' => 5, 'locationId' => 'WH1-ZONEA-A01-R01-S01-B01'],
            ['sku' => 'SKU4', 'quantity' => 3, 'locationId' => 'WH1-ZONEA-A02-R02-S01-B01']
        ];

        $optRes = $this->request('POST', '/api/warehouse-locations/optimize-pick-route', [
            'items' => $items
        ], $this->token);

        $this->assertEquals(200, $optRes['status']);
        $optimized = $optRes['body'][0]['items'];

        // Aisle 1 (odd) => rack ascending: R01 then R02
        $this->assertEquals('SKU3', $optimized[0]['sku']); // A01-R01
        $this->assertEquals('SKU2', $optimized[1]['sku']); // A01-R02

        // Aisle 2 (even) => rack descending: R02 then R01
        $this->assertEquals('SKU4', $optimized[2]['sku']); // A02-R02
        $this->assertEquals('SKU1', $optimized[3]['sku']); // A02-R01
    }

    public function testWarehouseLocationCoordinates(): void
    {
        $saveRes = $this->request('POST', '/api/warehouse-locations', [
            'warehouseId' => 'WH1',
            'zone'        => 'ZONEC',
            'aisle'       => 'A03',
            'rack'        => 'R04',
            'shelf'       => 'S05',
            'bin'         => 'B06',
            'maxWeightGrams' => 20000,
            'maxVolumeCubicMeters' => 2.5,
            'gridX'  => 10,
            'gridY'  => 15,
            'width'  => 2,
            'height' => 3
        ], $this->token);
        $this->assertEquals(200, $saveRes['status'], json_encode($saveRes));
        $this->assertEquals(10, $saveRes['body']['location']['gridX']);
        $this->assertEquals(15, $saveRes['body']['location']['gridY']);
        $this->assertEquals(2, $saveRes['body']['location']['width']);
        $this->assertEquals(3, $saveRes['body']['location']['height']);

        $listRes = $this->request('GET', '/api/warehouse-locations', [], $this->token);
        $this->assertEquals(200, $listRes['status']);
        $found = false;
        foreach ($listRes['body'] as $loc) {
            if ($loc['id'] === 'WH1-ZONEC-A03-R04-S05-B06') {
                $this->assertEquals(10, $loc['gridX']);
                $this->assertEquals(15, $loc['gridY']);
                $this->assertEquals(2, $loc['width']);
                $this->assertEquals(3, $loc['height']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8091' . $path;
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
