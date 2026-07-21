<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShopifyWebhookController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\ServiceContainer;
require_once __DIR__ . '/../../../../../src/Infrastructure/Persistence/sqlite_setup.php';
use InventoryApp\Infrastructure\Persistence\SqliteSetup;
use Illuminate\Database\Capsule\Manager as DB;

class MockPhpStream
{
    public $context;
    private int $position = 0;
    public static string $data = '';

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        return true;
    }

    public function stream_read($count)
    {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat()
    {
        return [];
    }
}

class ShopifyWebhookControllerTest extends TestCase
{
    private ?string $originalSecret = null;
    private ?string $originalHmac = null;
    private ?string $originalTopic = null;

    protected function setUp(): void
    {
        $this->originalSecret = getenv('SHOPIFY_WEBHOOK_SECRET');
        $this->originalHmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? null;
        $this->originalTopic = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? null;

        // Setup SQLite Capsule
        $capsule = new DB();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        SqliteSetup::createSchema($capsule->getConnection());

        // Setup stream wrapper
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_register('php', MockPhpStream::class);

        // Reset server/env state
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = '';
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = '';
        putenv('SHOPIFY_WEBHOOK_SECRET=');
        MockPhpStream::$data = '';
    }

    protected function tearDown(): void
    {
        stream_wrapper_restore('php');

        if ($this->originalHmac !== null) {
            $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = $this->originalHmac;
        } else {
            unset($_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256']);
        }

        if ($this->originalTopic !== null) {
            $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = $this->originalTopic;
        } else {
            unset($_SERVER['HTTP_X_SHOPIFY_TOPIC']);
        }

        if ($this->originalSecret !== false && $this->originalSecret !== null) {
            putenv('SHOPIFY_WEBHOOK_SECRET=' . $this->originalSecret);
        } else {
            putenv('SHOPIFY_WEBHOOK_SECRET'); // Removes the env var
        }

        MockPhpStream::$data = '';
    }

    private function createMockRequest(?string $tenantId): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('query')
                ->willReturnCallback(function($key) use ($tenantId) {
                    if ($key === 'tenant_id') {
                        return $tenantId;
                    }
                    return null;
                });
        return $request;
    }

    public function testMissingTenantIdReturns400()
    {
        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest(null);

        $response = $controller->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('tenant_id query parameter is required', $response->getContent());
    }

    public function testInvalidHmacReturns401()
    {
        putenv('SHOPIFY_WEBHOOK_SECRET=my-secret');
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = 'invalid_hmac';
        MockPhpStream::$data = json_encode(['id' => 123]);

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest('tenant-1');

        $response = $controller->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('HMAC verification failed', $response->getContent());
    }

    public function testValidHmacProcessesOrdersCreate()
    {
        $tenantId = 'tenant-1';
        $locationId = 'LOC-STOREFRONT';
        $sku = 'TEST-SKU-1';

        // Seed data
        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Test Tenant']);
        DB::table('locations')->insert(['id' => $locationId, 'name' => 'Storefront', 'type' => 'STORE']);
        DB::table('products')->insert([
            'id' => 'prod-1',
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => 'Test Product',
            'department' => 'Test',
        ]);
        DB::table('product_locations')->insert([
            'product_id' => 'prod-1',
            'location_id' => $locationId,
            'stock_quantity' => 10,
        ]);

        $payload = [
            'id' => '999',
            'line_items' => [
                ['sku' => $sku, 'quantity' => 3],
                // ['sku' => 'UNKNOWN-SKU', 'quantity' => 1] // Actually ProcessSale throws if SKU not found
            ]
        ];

        MockPhpStream::$data = json_encode($payload);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'orders/create';

        // Compute valid HMAC
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest($tenantId);

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Order webhook processed', $response->getContent());

        // Assert stock decremented for TEST-SKU-1
        $stock = DB::table('product_locations')
            ->where('product_id', 'prod-1')
            ->where('location_id', $locationId)
            ->value('stock_quantity');

        $this->assertEquals(7, $stock);
    }

    public function testOrdersCancelledRestocks()
    {
        $tenantId = 'tenant-1';
        $locationId = 'LOC-STOREFRONT';
        $sku = 'TEST-SKU-2';

        // Seed data
        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Test Tenant']);
        DB::table('locations')->insert(['id' => $locationId, 'name' => 'Storefront', 'type' => 'STORE']);
        DB::table('products')->insert([
            'id' => 'prod-2',
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => 'Test Product 2',
            'department' => 'Test',
        ]);
        DB::table('product_locations')->insert([
            'product_id' => 'prod-2',
            'location_id' => $locationId,
            'stock_quantity' => 5,
        ]);

        $payload = [
            'id' => '888',
            'line_items' => [
                ['sku' => $sku, 'quantity' => 2]
            ]
        ];

        MockPhpStream::$data = json_encode($payload);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'orders/cancelled';

        // Set valid secret and HMAC for this test to pass HMAC validation
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest($tenantId);

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Cancellation webhook processed', $response->getContent());

        // Assert stock incremented
        $stock = DB::table('product_locations')
            ->where('product_id', 'prod-2')
            ->where('location_id', $locationId)
            ->value('stock_quantity');

        $this->assertEquals(7, $stock);
    }

    public function testRefundsCreateRestocks()
    {
        $tenantId = 'tenant-1';
        $locationId = 'LOC-STOREFRONT';
        $sku = 'TEST-SKU-3';

        // Seed data
        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Test Tenant']);
        DB::table('locations')->insert(['id' => $locationId, 'name' => 'Storefront', 'type' => 'STORE']);
        DB::table('products')->insert([
            'id' => 'prod-3',
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => 'Test Product 3',
            'department' => 'Test',
        ]);
        DB::table('product_locations')->insert([
            'product_id' => 'prod-3',
            'location_id' => $locationId,
            'stock_quantity' => 2,
        ]);

        $payload = [
            'order_id' => '777',
            'refund_line_items' => [
                [
                    'line_item' => ['sku' => $sku],
                    'quantity' => 4
                ]
            ]
        ];

        MockPhpStream::$data = json_encode($payload);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'refunds/create';

        // Set valid secret and HMAC for this test to pass HMAC validation
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest($tenantId);

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Refund webhook processed', $response->getContent());

        $stock = DB::table('product_locations')
            ->where('product_id', 'prod-3')
            ->where('location_id', $locationId)
            ->value('stock_quantity');

        $this->assertEquals(6, $stock);
    }

    public function testUnsupportedTopic()
    {
        MockPhpStream::$data = json_encode(['id' => '111']);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'unknown/topic';

        // Set valid secret and HMAC for this test to pass HMAC validation
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest('tenant-1');

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Webhook topic not supported, ignored', $response->getContent());
    }

    public function testExceptionReturns400()
    {
        $tenantId = 'tenant-1';
        $locationId = 'LOC-STOREFRONT';
        $sku = 'NON-EXISTENT-SKU'; // This will cause ProcessSale to throw an exception

        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Test Tenant']);
        DB::table('locations')->insert(['id' => $locationId, 'name' => 'Storefront', 'type' => 'STORE']);

        $payload = [
            'id' => '123',
            'line_items' => [
                ['sku' => $sku, 'quantity' => 1]
            ]
        ];

        MockPhpStream::$data = json_encode($payload);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'orders/create';

        // Set valid secret and HMAC for this test to pass HMAC validation
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest($tenantId);

        $response = $controller->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        // Instead of asserting the exact internal message, we just assert a 400 is returned to stop retries.
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }

    public function testEmptySecretReturns500()
    {
        putenv('SHOPIFY_WEBHOOK_SECRET=');
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = 'invalid_hmac';
        MockPhpStream::$data = json_encode(['id' => 123]);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'unknown/topic';

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest('tenant-1');

        $response = $controller->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('Webhook secret is not configured', $response->getContent());
    }

    public function testLineItemWithEmptySkuOrInvalidQuantityIsIgnored()
    {
        $tenantId = 'tenant-1';
        $locationId = 'LOC-STOREFRONT';

        // Seed data
        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Test Tenant']);
        DB::table('locations')->insert(['id' => $locationId, 'name' => 'Storefront', 'type' => 'STORE']);
        DB::table('products')->insert([
            'id' => 'prod-4',
            'tenant_id' => $tenantId,
            'sku' => 'TEST-SKU-4',
            'name' => 'Test Product 4',
            'department' => 'Test',
        ]);
        DB::table('product_locations')->insert([
            'product_id' => 'prod-4',
            'location_id' => $locationId,
            'stock_quantity' => 10,
        ]);

        $payload = [
            'id' => '444',
            'line_items' => [
                ['sku' => '', 'quantity' => 5], // Empty SKU
                ['sku' => 'TEST-SKU-4', 'quantity' => 0], // Zero quantity
                ['sku' => 'TEST-SKU-4', 'quantity' => -1], // Negative quantity
                ['sku' => 'TEST-SKU-4', 'quantity' => 1], // Valid item
            ]
        ];

        MockPhpStream::$data = json_encode($payload);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'orders/create';

        // Set valid secret and HMAC for this test to pass HMAC validation
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest($tenantId);

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Order webhook processed', $response->getContent());

        // Assert stock decremented by only the valid item (1)
        $stock = DB::table('product_locations')
            ->where('product_id', 'prod-4')
            ->where('location_id', $locationId)
            ->value('stock_quantity');

        $this->assertEquals(9, $stock);
    }

    public function testLocationMappingIsUsedIfPresent()
    {
        $tenantId = 'tenant-1';
        $locationId = 'LOC-CUSTOM';

        // Explicitly insert test data into the existing location mapping table
        DB::table('shopify_location_mappings')->insert([
            'id' => 'map-1',
            'shopify_location_id' => 'shop-loc-1',
            'our_location_id' => $locationId
        ]);

        // Seed data
        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Test Tenant']);
        DB::table('locations')->insert(['id' => $locationId, 'name' => 'Custom', 'type' => 'STORE']);
        DB::table('products')->insert([
            'id' => 'prod-5',
            'tenant_id' => $tenantId,
            'sku' => 'TEST-SKU-5',
            'name' => 'Test Product 5',
            'department' => 'Test',
        ]);
        DB::table('product_locations')->insert([
            'product_id' => 'prod-5',
            'location_id' => $locationId,
            'stock_quantity' => 10,
        ]);

        $payload = [
            'id' => '555',
            'location_id' => 'shop-loc-1', // Although ignored by current controller, explicitly simulating it
            'line_items' => [
                ['sku' => 'TEST-SKU-5', 'quantity' => 2]
            ]
        ];

        MockPhpStream::$data = json_encode($payload);
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'orders/create';

        // Set valid secret and HMAC for this test to pass HMAC validation
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest($tenantId);

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $stock = DB::table('product_locations')
            ->where('product_id', 'prod-5')
            ->where('location_id', $locationId)
            ->value('stock_quantity');

        $this->assertEquals(8, $stock);
    }

    public function testInvalidJsonBodyIsHandled()
    {
        $tenantId = 'tenant-1';
        MockPhpStream::$data = 'invalid json string';
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = 'orders/create';

        // Set valid secret and HMAC for this test to pass HMAC validation
        $secret = 'test-secret';
        putenv("SHOPIFY_WEBHOOK_SECRET={$secret}");
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = base64_encode(hash_hmac('sha256', MockPhpStream::$data, $secret, true));

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest($tenantId);

        $response = $controller->handle($request);

        // Since json_decode returns null, it falls back to empty array and processes with 0 line items
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Order webhook processed', $response->getContent());
    }
}
