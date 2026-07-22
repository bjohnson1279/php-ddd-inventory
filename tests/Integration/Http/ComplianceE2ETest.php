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
        $dbConn = getenv('DB_CONNECTION') ?: 'sqlite';
        $dbDb   = getenv('DB_DATABASE') ?: __DIR__ . '/../../../database.sqlite';
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $dbUser = getenv('DB_USERNAME') ?: 'root';
        $dbPass = getenv('DB_PASSWORD') ?: '';

        // Assign a unique non-overlapping port number for this test file
        $command = "DB_CONNECTION={$dbConn} DB_DATABASE={$dbDb} DB_HOST={$dbHost} DB_USERNAME={$dbUser} DB_PASSWORD={$dbPass} php -S 127.0.0.1:8099 public/index.php > tests/Integration/Http/server_compliance.log 2>&1 & echo $!";
        $command = "php -S 127.0.0.1:8100 public/index.php > tests/Integration/Http/server_compliance.log 2>&1 & echo $!";

        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);

        // Wait for server to bind
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8099, $errno, $errstr, 0.1);
            $fp = @fsockopen('127.0.0.1', 8100, $errno, $errstr, 0.1);
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
        ]);
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
        ], $this->token);
        $this->assertEquals(201, $varRes['status']);

        // Setup location
        Capsule::table('locations')->insertOrIgnore([
            'id'   => 'LOC-COMP-1',
            'name' => 'LOC-COMP-1',
            'type' => 'WAREHOUSE',
        ]);

        // Receive stock
        $receiveRes = $this->request('POST', '/api/inventory/receive', [
            'sku'         => 'SKU-COMP-1',
            'quantity'    => 50,
            'location_id' => 'LOC-COMP-1'
        ], $this->token);
        $this->assertEquals(201, $receiveRes['status'], json_encode($receiveRes));

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
        $url = 'http://127.0.0.1:8099' . $path;
        $url = 'http://127.0.0.1:8100' . $path;
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
