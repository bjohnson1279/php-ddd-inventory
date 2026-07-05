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
        $this->assertEquals(200, $setupRes['status'], json_encode($setupRes));

        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => $this->email,
            'password'  => $this->password,
        ]);
        $this->assertEquals(200, $loginRes['status']);
        $this->token = $loginRes['body']['token'];

        // Configure mock env variables in php environment
        putenv("SHOPIFY_SHOP_URL=mock.myshopify.com");
        putenv("SHOPIFY_ACCESS_TOKEN=mock-token");
        putenv("QUICKBOOKS_ACCESS_TOKEN=mock-qbo-token");

        // Seed catalog product and variant for FK constraints
        $catalogProductId = uuidv4();
        Capsule::table('catalog_products')->insert([
            'id' => $catalogProductId,
            'name' => 'iPhone 15 Catalog',
            'description' => 'Test Description',
            'department' => 'Electronics',
            'tenant_id' => $this->tenantId
        ]);

        $catalogVariantId = uuidv4();
        Capsule::table('catalog_variants')->insert([
            'id' => $catalogVariantId,
            'product_id' => $catalogProductId,
            'sku' => 'SKU-DIFF',
            'attributes' => '{}',
            'price' => 999.00
        ]);

        // Seed product with a valid UUID
        $productId = uuidv4();
        Capsule::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $this->tenantId,
            'sku' => 'SKU-DIFF', // ends with -DIFF to mock Shopify mismatch
            'name' => 'iPhone 15',
            'department' => 'Electronics',
            'reorder_threshold' => 10,
            'version_id' => 1
        ]);

        // Seed shopify mappings
        Capsule::table('shopify_sku_mappings')->insert([
            'id' => uuidv4(),
            'sku' => 'SKU-DIFF',
            'shopify_inventory_item_id' => 'inv-item-123'
        ]);

        Capsule::table('shopify_location_mappings')->insert([
            'id' => uuidv4(),
            'our_location_id' => 'LOC-STOREFRONT',
            'shopify_location_id' => 'gid://shopify/Location/12345'
        ]);

        // Seed ledger entry with quantity
        Capsule::table('ledger_entries')->insert([
            'id' => uuidv4(),
            'tenant_id' => $this->tenantId,
            'variant_id' => $productId,
            'quantity' => 10,
            'reason' => 'opening_balance',
            'actor_id' => 'system',
            'metadata' => json_encode(['locationId' => 'default']),
            'occurred_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Seed journal entry without mapping
        Capsule::table('journal_entries')->insert([
            'id' => 'je-1',
            'tenant_id' => $this->tenantId,
            'entry_date' => date('Y-m-d'),
            'description' => 'Test unmapped journal',
            'method' => 'accrual',
            'lines' => '[]',
            'created_at' => date('Y-m-d H:i:s')
        ]);
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
        ];
    }
}
