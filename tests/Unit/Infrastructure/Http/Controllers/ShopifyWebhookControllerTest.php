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
    protected function setUp(): void
    {
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
        $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] = '';
        $_SERVER['HTTP_X_SHOPIFY_TOPIC'] = '';
        putenv('SHOPIFY_WEBHOOK_SECRET=');
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

        $controller = new ShopifyWebhookController();
        $request = $this->createMockRequest('tenant-1');

        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Webhook topic not supported, ignored', $response->getContent());
    }
}
