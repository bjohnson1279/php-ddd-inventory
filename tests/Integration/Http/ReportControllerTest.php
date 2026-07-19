<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ReportControllerTest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        $output = [];
        $command = "php -S 127.0.0.1:8089 public/index.php > tests/Integration/Http/server_report.log 2>&1 & echo $!";
        
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);
        
        // Wait for server to bind
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8089, $errno, $errstr, 0.1);
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
        DB::table('users')->delete();
        DB::table('user_roles')->delete();
        DB::table('tenants')->whereNotIn('id', ['test-tenant', 'system'])->delete();
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

    public function testValuationReportCalculations(): void
    {
        $productId = uuidv4();
        $sku = 'VAL-SKU-100';

        // 1. Seed Product
        DB::table('products')->insert([
            'id'                => $productId,
            'tenant_id'         => $this->tenantId,
            'sku'               => $sku,
            'name'              => 'Valuation Test Product',
            'department'        => 'GEN',
            'reorder_threshold' => 5,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        // 2. Seed Location Stock (15 units)
        DB::table('product_locations')->insert([
            'product_id'        => $productId,
            'location_id'       => 'LOC-INT',
            'stock_quantity'    => 15,
            'open_box_quantity' => 0,
            'damaged_quantity'  => 0,
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        // 3. Seed Cost Layers
        DB::table('inventory_cost_layers')->insert([
            [
                'id'                 => uuidv4(),
                'tenant_id'          => $this->tenantId,
                'variant_id'         => $sku,
                'original_quantity'  => 10,
                'remaining_quantity' => 10,
                'unit_cost_cents'    => 1000,
                'purchase_order_id'  => 'PO-1',
                'received_at'        => '2026-01-01 00:00:00'
            ],
            [
                'id'                 => uuidv4(),
                'tenant_id'          => $this->tenantId,
                'variant_id'         => $sku,
                'original_quantity'  => 10,
                'remaining_quantity' => 10,
                'unit_cost_cents'    => 1200,
                'purchase_order_id'  => 'PO-2',
                'received_at'        => '2026-02-01 00:00:00'
            ]
        ]);

        // 4. Request valuation report
        $res = $this->request('GET', '/api/reports/valuation', [], $this->token);

        $this->assertEquals(200, $res['status'], json_encode($res));

        $body = $res['body'];

        // Asserts
        $this->assertEquals(15, $body['total_items_count']);
        
        // FIFO Valuation for remaining inventory:
        // Newest layers: Layer 2 has 10 units ($120). Layer 1 has 5 units remaining ($50).
        // Total FIFO valuation = 10 * 1200 + 5 * 1000 = 17000 cents ($170.00)
        $this->assertEquals(17000, $body['total_valuation_fifo_cents']);

        // LIFO Valuation for remaining inventory:
        // Oldest layers: Layer 1 has 10 units ($100). Layer 2 has 5 units remaining ($60).
        // Total LIFO valuation = 10 * 1000 + 5 * 1200 = 16000 cents ($160.00)
        $this->assertEquals(16000, $body['total_valuation_lifo_cents']);

        // WAC Valuation:
        // Total layers qty = 20. Total layers value = 22000. Avg = 1100.
        // Total WAC valuation = 15 * 1100 = 16500 cents ($165.00)
        $this->assertEquals(16500, $body['total_valuation_wac_cents']);
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
