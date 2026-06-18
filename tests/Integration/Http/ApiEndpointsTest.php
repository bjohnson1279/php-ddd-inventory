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
        // Export environment variables for the test server
        putenv('SHOPIFY_WEBHOOK_SECRET=test-secret-env');

        // Start built-in PHP development server in the background on port 8085
        $output = [];
        $command = "php -S 127.0.0.1:8085 public/index.php > tests/Integration/Http/server_api.log 2>&1 & echo $!";
        
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

    public function testBarcodeScanningSSE(): void
    {
        $variantId = uuidv4();
        $barcodeValue = 'SCAN' . strval(rand(1000000000, 9999999999));

        // 1. Setup variant product in DB
        \Illuminate\Database\Capsule\Manager::table('catalog_products')->insert([
            'id' => $variantId,
            'name' => 'Scan Test Product',
            'description' => 'Test',
            'department' => 'GEN',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        \Illuminate\Database\Capsule\Manager::table('product_variants')->insert([
            'id' => $variantId,
            'product_id' => $variantId,
            'sku' => 'SKU-SCAN-1',
            'attributes' => '[]',
            'price' => 10.0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Assign Barcode
        $assignRes = $this->request('POST', '/api/barcodes/assign', [
            'variant_id' => $variantId,
            'value'      => $barcodeValue,
            'symbology'  => 'ean_13',
            'source'     => 'internal',
            'is_primary' => true,
        ], $this->token);
        $this->assertEquals(201, $assignRes['status'], json_encode($assignRes));

        // 3. Dispatch Barcode Scan via HTTP endpoint
        $scanRes = $this->request('POST', '/api/barcodes/scan', [
            'rawScan' => $barcodeValue,
            'context' => 'receiving',
            'payload' => [
                'location_id' => 'LOC-SCAN-1',
                'amount' => 5
            ]
        ], $this->token);

        $this->assertEquals(200, $scanRes['status'], json_encode($scanRes));
        $this->assertEquals('Scan processed.', $scanRes['body']['message']);
        $this->assertEquals('SKU-SCAN-1', $scanRes['body']['sku']);

        // 4. Query notifications stream mock (GET /api/notifications)
        $notifRes = $this->request('GET', '/api/notifications', [], $this->token);
        $this->assertEquals(200, $notifRes['status'], json_encode($notifRes));
        
        $found = false;
        foreach ($notifRes['body'] as $n) {
            if ($n['type'] === 'barcode_scanned') {
                $payload = json_decode($n['message'], true);
                if ($payload['scanValue'] === $barcodeValue) {
                    $found = true;
                    $this->assertEquals('receiving', $payload['context']);
                    $this->assertEquals('SKU-SCAN-1', $payload['sku']);
                    break;
                }
            }
        }
        $this->assertTrue($found, "Notification for scan event not found.");
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

    public function testShopifyWebhook(): void
    {
        $productId = uuidv4();
        $sku = 'SHPFY-SKU-100';

        // 1. Seed Product
        \Illuminate\Database\Capsule\Manager::table('products')->insert([
            'id'                => $productId,
            'tenant_id'         => $this->tenantId,
            'sku'               => $sku,
            'name'              => 'Shopify Webhook Test Product',
            'department'        => 'APP',
            'reorder_threshold' => 10,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        // 2. Seed Location Stock
        \Illuminate\Database\Capsule\Manager::table('product_locations')->insert([
            'product_id'        => $productId,
            'location_id'       => 'LOC-INT',
            'stock_quantity'    => 50,
            'open_box_quantity' => 0,
            'damaged_quantity'  => 0,
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        // 3. Register Location mapping so webhook resolves LOC-INT
        \Illuminate\Database\Capsule\Manager::table('shopify_location_mappings')->insert([
            'id'                  => uuidv4(),
            'our_location_id'     => 'LOC-INT',
            'shopify_location_id' => 'shopify-loc-1234',
            'created_at'          => date('Y-m-d H:i:s')
        ]);

        // 4. Send orders/create Webhook Request (without Bearer token)
        $payload = [
            'id' => 9988776655,
            'line_items' => [
                [
                    'sku' => $sku,
                    'quantity' => 5
                ]
            ]
        ];

        $jsonPayload = json_encode($payload);
        $secret = getenv('SHOPIFY_WEBHOOK_SECRET') ?: 'test-secret-env';
        // Mocking env variable wasn't working directly in the web server process
        // In the PHP internal server `php -S`, env vars are not always passed.
        // We'll let the application use whatever is defined, and sign it appropriately.
        // Wait, the webhook controller fetches it with `getenv()`.
        // If not set, it fails with 500 now.
        // Let's ensure the secret is set before making the request by restarting the server or passing it in.
        // Since we cannot restart the server here easily, the PHP internal server script `tests/Integration/Http/server.php`
        // will have the environment variables from when it was started.
        // We need to calculate the HMAC based on what the server expects.
        // If the server doesn't have it, we're in trouble.
        // Actually, we can just fetch the secret from the current environment,
        // assuming it was passed down when the test suite was started.
        $calculatedHmac = base64_encode(hash_hmac('sha256', $jsonPayload, $secret, true));

        $url = 'http://127.0.0.1:8085/api/webhooks/shopify?tenant_id=' . $this->tenantId;
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n" .
                            "X-Shopify-Topic: orders/create\r\n" .
                            "X-Shopify-Hmac-Sha256: {$calculatedHmac}\r\n",
                'method' => 'POST',
                'content' => $jsonPayload,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $statusCode = (int)$match[1];

        $this->assertEquals(200, $statusCode, $result);
        
        // 5. Verify stock decreased from 50 to 45
        $stockQty = (int)\Illuminate\Database\Capsule\Manager::table('product_locations')
            ->where('product_id', $productId)
            ->where('location_id', 'LOC-INT')
            ->value('stock_quantity');
        
        $this->assertEquals(45, $stockQty);

        // 6. Test cancellation webhook (orders/cancelled) to restock 5 items
        $cancelHmac = base64_encode(hash_hmac('sha256', $jsonPayload, $secret, true));
        $cancelOptions = [
            'http' => [
                'header' => "Content-Type: application/json\r\n" .
                            "X-Shopify-Topic: orders/cancelled\r\n" .
                            "X-Shopify-Hmac-Sha256: {$cancelHmac}\r\n",
                'method' => 'POST',
                'content' => $jsonPayload,
                'ignore_errors' => true
            ]
        ];

        $cancelContext = stream_context_create($cancelOptions);
        $cancelResult = file_get_contents($url, false, $cancelContext);

        preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
        $cancelStatusCode = (int)$match[1];

        $this->assertEquals(200, $cancelStatusCode, $cancelResult);

        // Verify stock restocked back to 50
        $newStockQty = (int)\Illuminate\Database\Capsule\Manager::table('product_locations')
            ->where('product_id', $productId)
            ->where('location_id', 'LOC-INT')
            ->value('stock_quantity');
        
        $this->assertEquals(50, $newStockQty);
    }

    public function testNotificationEndpoints(): void
    {
        // 1. Check notifications initially empty
        $listRes = $this->request('GET', '/api/notifications', [], $this->token);
        $this->assertEquals(200, $listRes['status']);
        $this->assertEmpty($listRes['body']['notifications']);

        // 2. Trigger an event that creates a notification (e.g. Receive stock)
        $productId = uuidv4();
        $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(4)));

        \Illuminate\Database\Capsule\Manager::table('products')->insert([
            'id'                => $productId,
            'tenant_id'         => $this->tenantId,
            'sku'               => $sku,
            'name'              => 'Notif Prod',
            'department'        => 'GEN',
            'reorder_threshold' => 10,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        \Illuminate\Database\Capsule\Manager::table('product_locations')->insert([
            'product_id'        => $productId,
            'location_id'       => 'LOC-INT',
            'stock_quantity'    => 0,
            'open_box_quantity' => 0,
            'damaged_quantity'  => 0,
            'updated_at'        => date('Y-m-d H:i:s')
        ]);

        // Receive stock
        $receiveRes = $this->request('POST', '/api/inventory/receive', [
            'sku'         => $sku,
            'quantity'    => 10,
            'location_id' => 'LOC-INT',
        ], $this->token);
        $this->assertEquals(200, $receiveRes['status'], json_encode($receiveRes));

        // 3. Check notifications now has 1 item
        $listRes2 = $this->request('GET', '/api/notifications', [], $this->token);
        $this->assertEquals(200, $listRes2['status']);
        $this->assertCount(1, $listRes2['body']['notifications']);
        $notif = $listRes2['body']['notifications'][0];
        $this->assertEquals('Stock Received', $notif['title']);
        $this->assertFalse((bool)$notif['is_read']);

        // 4. Test SSE subscribe endpoint
        $stream = @fopen("http://127.0.0.1:8085/api/notifications/subscribe?token={$this->token}&test=1", 'r');
        $this->assertNotFalse($stream, "Should connect to SSE stream");
        $firstLine = fgets($stream);
        fclose($stream);
        $this->assertStringContainsString('event: connected', $firstLine);

        // 5. Mark as read
        $readRes = $this->request('POST', "/api/notifications/{$notif['id']}/read", [], $this->token);
        $this->assertEquals(200, $readRes['status']);

        // Check is_read is true
        $listRes3 = $this->request('GET', '/api/notifications', [], $this->token);
        $this->assertTrue((bool)$listRes3['body']['notifications'][0]['is_read']);

        // 6. Mark all as read
        $readAllRes = $this->request('POST', '/api/notifications/read-all', [], $this->token);
        $this->assertEquals(200, $readAllRes['status']);
    }

    public function testKitAssemblyAndDisassembly(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $compAId = uuidv4();
        $compBId = uuidv4();
        $kitProductId = uuidv4();
        $compASku = "COMP-A-{$suffix}";
        $compBSku = "COMP-B-{$suffix}";
        $kitSku = "KIT-BUNDLE-{$suffix}";
        $locationId = "LOC-INT";

        // Seed products
        \Illuminate\Database\Capsule\Manager::table('products')->insert([
            ['id' => $compAId, 'tenant_id' => $this->tenantId, 'sku' => $compASku, 'name' => 'Component A', 'department' => 'APP', 'reorder_threshold' => 10, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $compBId, 'tenant_id' => $this->tenantId, 'sku' => $compBSku, 'name' => 'Component B', 'department' => 'APP', 'reorder_threshold' => 10, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $kitProductId, 'tenant_id' => $this->tenantId, 'sku' => $kitSku, 'name' => 'Kit Bundle', 'department' => 'APP', 'reorder_threshold' => 10, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        // Seed product locations
        \Illuminate\Database\Capsule\Manager::table('product_locations')->insert([
            ['product_id' => $compAId, 'location_id' => $locationId, 'stock_quantity' => 10, 'open_box_quantity' => 0, 'damaged_quantity' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            ['product_id' => $compBId, 'location_id' => $locationId, 'stock_quantity' => 20, 'open_box_quantity' => 0, 'damaged_quantity' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            ['product_id' => $kitProductId, 'location_id' => $locationId, 'stock_quantity' => 0, 'open_box_quantity' => 0, 'damaged_quantity' => 0, 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        // Seed ledger entries for component stocks
        \Illuminate\Database\Capsule\Manager::table('ledger_entries')->insert([
            ['id' => uuidv4(), 'tenant_id' => $this->tenantId, 'variant_id' => $compAId, 'quantity' => 10, 'reason' => 'purchase_receipt', 'actor_id' => 'system', 'occurred_at' => date('Y-m-d H:i:s')],
            ['id' => uuidv4(), 'tenant_id' => $this->tenantId, 'variant_id' => $compBId, 'quantity' => 20, 'reason' => 'purchase_receipt', 'actor_id' => 'system', 'occurred_at' => date('Y-m-d H:i:s')]
        ]);

        // Seed costing layers for COMP-A (unit cost 100) and COMP-B (unit cost 200)
        \Illuminate\Database\Capsule\Manager::table('inventory_cost_layers')->insert([
            ['id' => uuidv4(), 'variant_id' => $compAId, 'tenant_id' => $this->tenantId, 'original_quantity' => 10, 'remaining_quantity' => 10, 'unit_cost_cents' => 100, 'received_at' => date('Y-m-d H:i:s'), 'purchase_order_id' => 'PO-1'],
            ['id' => uuidv4(), 'variant_id' => $compBId, 'tenant_id' => $this->tenantId, 'original_quantity' => 20, 'remaining_quantity' => 20, 'unit_cost_cents' => 200, 'received_at' => date('Y-m-d H:i:s'), 'purchase_order_id' => 'PO-2']
        ]);

        // Create Kit formula
        $kitRes = $this->request('POST', '/api/kits', [
            'sku'  => $kitSku,
            'name' => 'Kit Bundle Formula',
        ], $this->token);
        $this->assertEquals(201, $kitRes['status']);
        $kitId = $kitRes['body']['id'];

        $this->request('POST', "/api/kits/{$kitId}/components", ['variant_id' => $compAId, 'quantity' => 2], $this->token);
        $this->request('POST', "/api/kits/{$kitId}/components", ['variant_id' => $compBId, 'quantity' => 1], $this->token);

        // Invite staff/viewer user to test RBAC
        $inviteRes = $this->request('POST', '/api/users', [
            'email' => "viewer-{$suffix}@example.com",
        ], $this->token);
        $this->assertEquals(201, $inviteRes['status']);
        $viewerUserId = $inviteRes['body']['user_id'];
        $tempPassword = $inviteRes['body']['temporary_password'];

        $loginRes = $this->request('POST', '/api/auth/login', [
            'tenant_id' => $this->tenantId,
            'email'     => "viewer-{$suffix}@example.com",
            'password'  => $tempPassword,
        ]);
        $viewerToken = $loginRes['body']['token'];

        // Strip roles
        \Illuminate\Database\Capsule\Manager::table('user_roles')->where('user_id', $viewerUserId)->delete();

        // 3. Test RBAC denial on assemble/disassemble (no role/permission)
        $unauthRes1 = $this->request('POST', '/api/kits/assemble', [
            'kitSku' => $kitSku,
            'quantity' => 2,
            'locationId' => $locationId,
            'referenceId' => 'REF-ASM-TEST'
        ], $viewerToken);
        $this->assertEquals(403, $unauthRes1['status']);

        $unauthRes2 = $this->request('POST', '/api/kits/disassemble', [
            'kitSku' => $kitSku,
            'quantity' => 2,
            'locationId' => $locationId,
            'referenceId' => 'REF-DIS-TEST'
        ], $viewerToken);
        $this->assertEquals(403, $unauthRes2['status']);

        // 4. Assemble Kit (2 units) via admin token
        // Needs 2 * 2 = 4 units of COMP-A (cost 4 * 100 = 400) and 2 * 1 = 2 units of COMP-B (cost 2 * 200 = 400). Total cost = 800.
        $assembleRes = $this->request('POST', '/api/kits/assemble', [
            'kitSku' => $kitSku,
            'quantity' => 2,
            'locationId' => $locationId,
            'referenceId' => 'REF-ASM-1'
        ], $this->token);
        $this->assertEquals(200, $assembleRes['status'], json_encode($assembleRes));

        // Verify product location stocks: COMP-A: 6, COMP-B: 18, KIT: 2
        $this->assertEquals(6, \Illuminate\Database\Capsule\Manager::table('product_locations')->where('product_id', $compAId)->where('location_id', $locationId)->value('stock_quantity'));
        $this->assertEquals(18, \Illuminate\Database\Capsule\Manager::table('product_locations')->where('product_id', $compBId)->where('location_id', $locationId)->value('stock_quantity'));
        $this->assertEquals(2, \Illuminate\Database\Capsule\Manager::table('product_locations')->where('product_id', $kitProductId)->where('location_id', $locationId)->value('stock_quantity'));

        // Verify ledger entries stock: COMP-A: 6, COMP-B: 18, KIT: 2
        $this->assertEquals(6, \Illuminate\Database\Capsule\Manager::table('ledger_entries')->where('tenant_id', $this->tenantId)->where('variant_id', $compAId)->sum('quantity'));
        $this->assertEquals(18, \Illuminate\Database\Capsule\Manager::table('ledger_entries')->where('tenant_id', $this->tenantId)->where('variant_id', $compBId)->sum('quantity'));
        $this->assertEquals(2, \Illuminate\Database\Capsule\Manager::table('ledger_entries')->where('tenant_id', $this->tenantId)->where('variant_id', $kitProductId)->sum('quantity'));

        // Verify kit costing layer unit cost is 400 (800 / 2)
        $kitLayerVal = \Illuminate\Database\Capsule\Manager::table('inventory_cost_layers')
            ->where('tenant_id', $this->tenantId)
            ->where('variant_id', $kitProductId)
            ->first();
        $this->assertNotNull($kitLayerVal);
        $this->assertEquals(2, $kitLayerVal->remaining_quantity);
        $this->assertEquals(400, $kitLayerVal->unit_cost_cents);

        // Verify Journal entries are balanced (Debit 1200, Credit 1210 for 800)
        $journal = \Illuminate\Database\Capsule\Manager::table('journal_entries')
            ->where('tenant_id', $this->tenantId)
            ->where('reference_id', 'REF-ASM-1')
            ->first();
        $this->assertNotNull($journal);
        $lines = json_decode($journal->lines, true);
        $this->assertCount(2, $lines);
        $debitLine = null;
        $creditLine = null;
        foreach ($lines as $line) {
            if ($line['account'] === '1200') $debitLine = $line;
            if ($line['account'] === '1210') $creditLine = $line;
        }
        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals(800, $debitLine['amount']);
        $this->assertEquals('debit', $debitLine['type']);
        $this->assertEquals(800, $creditLine['amount']);
        $this->assertEquals('credit', $creditLine['type']);

        // 5. Disassemble Kit (2 units)
        $disassembleRes = $this->request('POST', '/api/kits/disassemble', [
            'kitSku' => $kitSku,
            'quantity' => 2,
            'locationId' => $locationId,
            'referenceId' => 'REF-DIS-1'
        ], $this->token);
        $this->assertEquals(200, $disassembleRes['status'], json_encode($disassembleRes));

        // Verify product location stocks: COMP-A: 10, COMP-B: 20, KIT: 0
        $this->assertEquals(10, \Illuminate\Database\Capsule\Manager::table('product_locations')->where('product_id', $compAId)->where('location_id', $locationId)->value('stock_quantity'));
        $this->assertEquals(20, \Illuminate\Database\Capsule\Manager::table('product_locations')->where('product_id', $compBId)->where('location_id', $locationId)->value('stock_quantity'));
        $this->assertEquals(0, \Illuminate\Database\Capsule\Manager::table('product_locations')->where('product_id', $kitProductId)->where('location_id', $locationId)->value('stock_quantity'));

        // Verify ledger entries stock: COMP-A: 10, COMP-B: 20, KIT: 0
        $this->assertEquals(10, \Illuminate\Database\Capsule\Manager::table('ledger_entries')->where('tenant_id', $this->tenantId)->where('variant_id', $compAId)->sum('quantity'));
        $this->assertEquals(20, \Illuminate\Database\Capsule\Manager::table('ledger_entries')->where('tenant_id', $this->tenantId)->where('variant_id', $compBId)->sum('quantity'));
        $this->assertEquals(0, \Illuminate\Database\Capsule\Manager::table('ledger_entries')->where('tenant_id', $this->tenantId)->where('variant_id', $kitProductId)->sum('quantity'));

        // Verify disassembly Journal entries are balanced (Debit 1210, Credit 1200 for 800)
        $disJournal = \Illuminate\Database\Capsule\Manager::table('journal_entries')
            ->where('tenant_id', $this->tenantId)
            ->where('reference_id', 'REF-DIS-1')
            ->first();
        $this->assertNotNull($disJournal);
        $disLines = json_decode($disJournal->lines, true);
        $this->assertCount(2, $disLines);
        $debitLinePost = null;
        $creditLinePost = null;
        foreach ($disLines as $line) {
            if ($line['account'] === '1210') $debitLinePost = $line;
            if ($line['account'] === '1200') $creditLinePost = $line;
        }
        $this->assertNotNull($debitLinePost);
        $this->assertNotNull($creditLinePost);
        $this->assertEquals(800, $debitLinePost['amount']);
        $this->assertEquals('debit', $debitLinePost['type']);
        $this->assertEquals(800, $creditLinePost['amount']);
        $this->assertEquals('credit', $creditLinePost['type']);
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
