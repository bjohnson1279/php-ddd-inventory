<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ApiEndpointsTest extends TestCase
{
    private static ?int $pid = null;
    private string $tenantId;
    private string $email;
    private string $password;
    private ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        // Start built-in PHP development server in the background on port 8085
        $output = [];
        $command = "php -S 127.0.0.1:8085 public/index.php > /dev/null 2>&1 & echo $!";
        
        exec($command, $output);
        self::$pid = (int)($output[0] ?? 0);
        
        // Wait for server to bind
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', 8085, $errno, $errstr, 0.1);
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
        $this->assertNotEmpty($loginRes['body']['token']);
        $this->token = $loginRes['body']['token'];
    }

    public function testBarcodeRegistryEndpoints(): void
    {
        $variantId = uuidv4();
        $barcodeValue = '978' . strval(rand(1000000000, 9999999999));

        // 1. Assign Barcode
        $assignRes = $this->request('POST', '/api/barcodes/assign', [
            'variant_id' => $variantId,
            'value'      => $barcodeValue,
            'symbology'  => 'ean_13',
            'source'     => 'internal',
            'is_primary' => true,
        ], $this->token);

        $this->assertEquals(201, $assignRes['status'], json_encode($assignRes));

        // 2. Lookup Barcode
        $lookupRes = $this->request('GET', "/api/barcodes/lookup?value={$barcodeValue}", [], $this->token);
        $this->assertEquals(200, $lookupRes['status'], json_encode($lookupRes));
        $this->assertEquals($variantId, $lookupRes['body']['variant_id']);

        // 3. Get Variant Set
        $setRes = $this->request('GET', "/api/barcodes/variants/{$variantId}", [], $this->token);
        $this->assertEquals(200, $setRes['status'], json_encode($setRes));
        $this->assertCount(1, $setRes['body']['assignments']);
        $this->assertEquals($barcodeValue, $setRes['body']['assignments'][0]['value']);
        $this->assertTrue($setRes['body']['assignments'][0]['is_primary']);
    }

    public function testSerialNumberEndpoints(): void
    {
        $variantId = uuidv4();
        $serial = 'SN-HTTP-TEST-999';

        // 1. Register Serial
        $regRes = $this->request('POST', '/api/serials', [
            'variant_id'    => $variantId,
            'serial_number' => $serial,
            'location_id'   => 'LOC-INT',
        ], $this->token);

        $this->assertEquals(201, $regRes['status'], json_encode($regRes));
        $itemId = $regRes['body']['id'];
        $this->assertNotEmpty($itemId);

        // 2. Lookup Serial
        $lookupRes = $this->request('GET', "/api/serials/lookup?serial_number={$serial}", [], $this->token);
        $this->assertEquals(200, $lookupRes['status'], json_encode($lookupRes));
        $this->assertEquals('pending', $lookupRes['body']['status']);

        // 3. Receive Serial
        $receiveRes = $this->request('POST', "/api/serials/{$itemId}/receive", [
            'location_id'       => 'LOC-INT',
            'purchase_order_id' => 'PO-HTTP-100',
            'unit_cost_cents'   => 2500,
        ], $this->token);
        $this->assertEquals(200, $receiveRes['status'], json_encode($receiveRes));

        // 4. Lookup again (should be in_stock)
        $lookupRes2 = $this->request('GET', "/api/serials/lookup?serial_number={$serial}", [], $this->token);
        $this->assertEquals('in_stock', $lookupRes2['body']['status']);

        // 5. Sell Serial
        $sellRes = $this->request('POST', "/api/serials/{$itemId}/sell", [
            'sale_id' => 'SALE-HTTP-200',
        ], $this->token);
        $this->assertEquals(200, $sellRes['status'], json_encode($sellRes));

        // 6. Accept Return
        $returnRes = $this->request('POST', "/api/serials/{$itemId}/return", [
            'return_id' => 'RET-HTTP-300',
        ], $this->token);
        $this->assertEquals(200, $returnRes['status'], json_encode($returnRes));

        // 7. Restock
        $restockRes = $this->request('POST', "/api/serials/{$itemId}/restock", [
            'return_id'                 => 'RET-HTTP-300',
            'restocked_unit_cost_cents' => 2400,
        ], $this->token);
        $this->assertEquals(200, $restockRes['status'], json_encode($restockRes));

        // 8. Write Off
        $writeOffRes = $this->request('POST', "/api/serials/{$itemId}/write-off", [
            'reason' => 'Damaged in store inspection',
        ], $this->token);
        $this->assertEquals(200, $writeOffRes['status'], json_encode($writeOffRes));

        // 9. List by variant
        $listRes = $this->request('GET', "/api/serials/variants/{$variantId}", [], $this->token);
        $this->assertEquals(200, $listRes['status'], json_encode($listRes));
        $this->assertCount(1, $listRes['body']['items']);

        // 10. Count by status
        $countRes = $this->request('GET', "/api/serials/variants/{$variantId}/count?status=written_off", [], $this->token);
        $this->assertEquals(200, $countRes['status'], json_encode($countRes));
        $this->assertEquals(1, $countRes['body']['count']);
    }

    public function testStockOnboardingEndpoints(): void
    {
        // 1. Create onboarding
        $createRes = $this->request('POST', '/api/onboardings', [
            'location_id' => 'LOC-INT',
            'as_of_date'  => '2026-05-29',
        ], $this->token);

        $this->assertEquals(201, $createRes['status'], json_encode($createRes));
        $onboardingId = $createRes['body']['id'];

        // 2. Set item
        $variantId = uuidv4();
        $setItemRes = $this->request('POST', "/api/onboardings/{$onboardingId}/items", [
            'variant_id'      => $variantId,
            'quantity'        => 50,
            'unit_cost_cents' => 1250,
        ], $this->token);
        $this->assertEquals(200, $setItemRes['status'], json_encode($setItemRes));

        // 3. Show details
        $showRes = $this->request('GET', "/api/onboardings/{$onboardingId}", [], $this->token);
        $this->assertEquals(200, $showRes['status'], json_encode($showRes));
        $this->assertEquals('draft', $showRes['body']['status']);
        $this->assertCount(1, $showRes['body']['items']);
        $this->assertEquals($variantId, $showRes['body']['items'][0]['variant_id']);

        // 4. Submit
        $submitRes = $this->request('POST', "/api/onboardings/{$onboardingId}/submit", [], $this->token);
        $this->assertEquals(200, $submitRes['status'], json_encode($submitRes));

        // 5. Show details after submit (should be submitted)
        $showRes2 = $this->request('GET', "/api/onboardings/{$onboardingId}", [], $this->token);
        $this->assertEquals('submitted', $showRes2['body']['status']);
    }

    public function testJournalEndpoints(): void
    {
        // 1. Record Journal Entry
        $recordRes = $this->request('POST', '/api/journal/entries', [
            'date'        => '2026-05-29',
            'description' => 'E2E Manual entry',
            'method'      => 'accrual',
            'lines'       => [
                ['account' => '1200', 'amount' => 50000, 'type' => 'debit', 'memo' => 'DR inventory'],
                ['account' => '1000', 'amount' => 50000, 'type' => 'credit', 'memo' => 'CR cash'],
            ],
        ], $this->token);

        $this->assertEquals(201, $recordRes['status'], json_encode($recordRes));
        $entryId = $recordRes['body']['id'];
        $this->assertNotEmpty($entryId);

        // 2. List Journal Entries
        $listRes = $this->request('GET', '/api/journal/entries', [], $this->token);
        $this->assertEquals(200, $listRes['status'], json_encode($listRes));
        $this->assertGreaterThanOrEqual(1, count($listRes['body']['entries']));
    }

    public function testUomEndpoints(): void
    {
        $variantId = uuidv4();

        // 1. Create Product UoM Configuration
        $createRes = $this->request('POST', '/api/uom/configurations', [
            'variant_id' => $variantId,
            'base_unit'  => [
                'name'         => 'Each',
                'abbreviation' => 'ea',
                'category'     => 'discrete',
            ],
        ], $this->token);

        $this->assertEquals(201, $createRes['status'], json_encode($createRes));
        $configId = $createRes['body']['id'];
        $this->assertNotEmpty($configId);

        // 2. Add Conversion Rule
        $ruleRes = $this->request('POST', "/api/uom/configurations/{$configId}/rules", [
            'unit' => [
                'name'         => 'Case',
                'abbreviation' => 'cs',
                'category'     => 'discrete',
            ],
            'factor_to_base' => 24.0,
            'label'          => 'Case of 24',
        ], $this->token);
        $this->assertEquals(200, $ruleRes['status'], json_encode($ruleRes));

        // 3. Set Purchase and Sale Units
        $unitRes = $this->request('POST', "/api/uom/configurations/{$configId}/units", [
            'purchase_unit' => [
                'name'         => 'Case',
                'abbreviation' => 'cs',
                'category'     => 'discrete',
            ],
            'sale_unit' => [
                'name'         => 'Each',
                'abbreviation' => 'ea',
                'category'     => 'discrete',
            ],
        ], $this->token);
        $this->assertEquals(200, $unitRes['status'], json_encode($unitRes));

        // 4. Show Details by Variant
        $showRes = $this->request('GET', "/api/uom/configurations/variants/{$variantId}", [], $this->token);
        $this->assertEquals(200, $showRes['status'], json_encode($showRes));
        $this->assertEquals($configId, $showRes['body']['id']);
        $this->assertEquals('Case', $showRes['body']['purchase_unit']['name']);
        $this->assertEquals('Each', $showRes['body']['sale_unit']['name']);
        $this->assertCount(1, $showRes['body']['rules']);

        // 5. Show Details by ID
        $showIdRes = $this->request('GET', "/api/uom/configurations/{$configId}", [], $this->token);
        $this->assertEquals(200, $showIdRes['status'], json_encode($showIdRes));
        $this->assertEquals($variantId, $showIdRes['body']['variant_id']);

        // 6. Remove Conversion Rule
        $removeRes = $this->request('DELETE', "/api/uom/configurations/{$configId}/rules", [
            'unit' => [
                'name'         => 'Case',
                'abbreviation' => 'cs',
                'category'     => 'discrete',
            ],
        ], $this->token);
        $this->assertEquals(200, $removeRes['status'], json_encode($removeRes));

        // 7. Show Details again
        $showRes2 = $this->request('GET', "/api/uom/configurations/{$configId}", [], $this->token);
        $this->assertCount(0, $showRes2['body']['rules']);
    }

    public function testKitEndpoints(): void
    {
        // 1. Create Kit
        $sku = 'KIT-' . strtoupper(bin2hex(random_bytes(4)));
        $createRes = $this->request('POST', '/api/kits', [
            'sku'  => $sku,
            'name' => 'Test Bundle Kit',
        ], $this->token);

        $this->assertEquals(201, $createRes['status'], json_encode($createRes));
        $kitId = $createRes['body']['id'];
        $this->assertNotEmpty($kitId);

        // 2. Add Component
        $variantId1 = uuidv4();
        $compRes = $this->request('POST', "/api/kits/{$kitId}/components", [
            'variant_id' => $variantId1,
            'quantity'   => 3,
        ], $this->token);
        $this->assertEquals(200, $compRes['status'], json_encode($compRes));

        // 3. Show Details by ID
        $showRes = $this->request('GET', "/api/kits/{$kitId}", [], $this->token);
        $this->assertEquals(200, $showRes['status'], json_encode($showRes));
        $this->assertEquals($sku, $showRes['body']['sku']);
        $this->assertCount(1, $showRes['body']['components']);
        $this->assertEquals($variantId1, $showRes['body']['components'][0]['variant_id']);
        $this->assertEquals(3, $showRes['body']['components'][0]['quantity']);

        // 4. Show Details by SKU
        $showSkuRes = $this->request('GET', "/api/kits/sku/{$sku}", [], $this->token);
        $this->assertEquals(200, $showSkuRes['status'], json_encode($showSkuRes));
        $this->assertEquals($kitId, $showSkuRes['body']['id']);

        // 5. Sell Kit (Inventory decrement verification)
        // First, let's put some stock in the ledger for variantId1 so decrement doesn't fail
        $ledgerEntryId = uuidv4();
        \Illuminate\Database\Capsule\Manager::table('ledger_entries')->insert([
            'id'          => $ledgerEntryId,
            'tenant_id'   => $this->tenantId,
            'variant_id'  => $variantId1,
            'quantity'    => 10,
            'reason'      => 'purchase_receipt',
            'actor_id'    => 'system',
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);

        $sellRes = $this->request('POST', "/api/kits/{$kitId}/sell", [
            'quantity' => 2,
            'sale_id'  => 'SALE-KIT-E2E-100',
        ], $this->token);
        $this->assertEquals(200, $sellRes['status'], json_encode($sellRes));

        // Verify ledger was decremented: variant1 component quantity 3 * 2 kit sales = 6 decremented.
        // Starting with 10 base units, remaining should be 4.
        $qty = (int)\Illuminate\Database\Capsule\Manager::table('ledger_entries')
            ->where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId1)
            ->sum('quantity');
        $this->assertEquals(4, $qty);
    }

    private function request(string $method, string $path, array $body = [], ?string $token = null): array
    {
        $url = 'http://127.0.0.1:8085' . $path;
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
