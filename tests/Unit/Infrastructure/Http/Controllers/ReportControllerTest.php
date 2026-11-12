<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ReportController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
require_once __DIR__ . '/../../../../../src/Infrastructure/Persistence/sqlite_setup.php';
use InventoryApp\Infrastructure\Persistence\SqliteSetup;
use Illuminate\Database\Capsule\Manager as DB;

class ReportControllerTest extends TestCase
{
    private ReportController $controller;
    private $requestMock;
    private ?string $origAuthTenantId = null;

    protected function setUp(): void
    {
        $this->controller = new ReportController();
        $this->requestMock = $this->createMock(RequestInterface::class);

        $this->origAuthTenantId = $_SERVER['auth.tenant_id'] ?? null;

        $capsule = new DB();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        SqliteSetup::createSchema($capsule->getConnection());
    }

    protected function tearDown(): void
    {
        if ($this->origAuthTenantId !== null) {
            $_SERVER['auth.tenant_id'] = $this->origAuthTenantId;
        } else {
            unset($_SERVER['auth.tenant_id']);
        }

        parent::tearDown();
    }

    public function test_valuation_returns_403_when_auth_tenant_is_missing(): void
    {
        unset($_SERVER['auth.tenant_id']);

        $response = $this->controller->valuation($this->requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized access to tenant report', $content['error']);
    }

    public function test_valuation_returns_403_when_auth_tenant_mismatches(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-different';

        $response = $this->controller->valuation($this->requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized access to tenant report', $content['error']);
    }

    public function test_valuation_succeeds_with_system_tenant_auth(): void
    {
        $_SERVER['auth.tenant_id'] = 'system';

        $response = $this->controller->valuation($this->requestMock, 'tenant-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals(0, $content['total_items_count']);
        $this->assertEquals(0, $content['total_valuation_wac_cents']);
    }

    public function test_valuation_calculates_fifo_lifo_wac_and_locations(): void
    {
        $tenantId = 'tenant-1';
        $_SERVER['auth.tenant_id'] = $tenantId;

        DB::table('locations')->insert([
            ['id' => 'LOC-1', 'name' => 'Main Warehouse', 'type' => 'WAREHOUSE'],
            ['id' => 'LOC-2', 'name' => 'Storefront', 'type' => 'STORE']
        ]);

        DB::table('products')->insert([
            [
                'id' => 'prod-1',
                'tenant_id' => $tenantId,
                'sku' => 'SKU-A',
                'name' => 'Product A',
                'department' => 'GEN',
                'reorder_threshold' => 10,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'prod-2',
                'tenant_id' => $tenantId,
                'sku' => 'SKU-B',
                'name' => 'Product B',
                'department' => 'GEN',
                'reorder_threshold' => 5,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);

        DB::table('product_locations')->insert([
            ['product_id' => 'prod-1', 'location_id' => 'LOC-1', 'stock_quantity' => 10, 'updated_at' => date('Y-m-d H:i:s')],
            ['product_id' => 'prod-1', 'location_id' => 'LOC-2', 'stock_quantity' => 5, 'updated_at' => date('Y-m-d H:i:s')],
            ['product_id' => 'prod-2', 'location_id' => 'LOC-1', 'stock_quantity' => 2, 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        // Cost layers for SKU-A:
        // Older: 10 units @ 1000 cents received 2026-01-01
        // Newer: 10 units @ 1200 cents received 2026-02-01
        DB::table('inventory_cost_layers')->insert([
            [
                'id' => 'layer-1',
                'tenant_id' => $tenantId,
                'variant_id' => 'SKU-A',
                'original_quantity' => 10,
                'remaining_quantity' => 10,
                'unit_cost_cents' => 1000,
                'purchase_order_id' => 'PO-1',
                'received_at' => '2026-01-01 00:00:00'
            ],
            [
                'id' => 'layer-2',
                'tenant_id' => $tenantId,
                'variant_id' => 'SKU-A',
                'original_quantity' => 10,
                'remaining_quantity' => 10,
                'unit_cost_cents' => 1200,
                'purchase_order_id' => 'PO-2',
                'received_at' => '2026-02-01 00:00:00'
            ]
        ]);

        // SKU-B has no cost layers; should fallback to catalog_variants price
        DB::table('catalog_variants')->insert([
            'id' => 'var-1',
            'product_id' => 'prod-2',
            'sku' => 'SKU-B',
            'price' => 25.50
        ]);

        // Insert recent activity
        DB::table('inventory_transactions')->insert([
            [
                'id' => 'tx-1',
                'tenant_id' => $tenantId,
                'product_id' => 'prod-1',
                'type' => 'RECEIPT',
                'quantity_change' => 15,
                'condition' => 'NEW',
                'created_at' => '2026-02-01 12:00:00'
            ],
            [
                'id' => 'tx-2',
                'tenant_id' => $tenantId,
                'product_id' => 'prod-2',
                'type' => 'RECEIPT',
                'quantity_change' => 2,
                'condition' => 'NEW',
                'created_at' => '2026-02-02 12:00:00'
            ]
        ]);

        $response = $this->controller->valuation($this->requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        // Product A total stock: 15. Reorder threshold: 10 (not low stock, 15 > 10).
        // Product B total stock: 2. Reorder threshold: 5 (low stock! 2 <= 5).
        $this->assertEquals(17, $data['total_items_count']);
        $this->assertEquals(1, $data['low_stock_alerts_count']);
        $this->assertCount(1, $data['low_stock_items']);
        $this->assertEquals('SKU-B', $data['low_stock_items'][0]['sku']);

        // SKU-A:
        // WAC: (10*1000 + 10*1200) / 20 = 1100 cents.
        // SKU-A total stock = 15. Stock * WAC = 15 * 1100 = 16500.
        // FIFO: sorted received_at DESC: 10 units @ 1200 + 5 units @ 1000 = 12000 + 5000 = 17000.
        // LIFO: sorted received_at ASC: 10 units @ 1000 + 5 units @ 1200 = 10000 + 6000 = 16000.

        // SKU-B:
        // No cost layers. Catalog price: $25.50 -> 2550 cents.
        // SKU-B total stock = 2. Stock * WAC = 2 * 2550 = 5100 cents.
        // FIFO for SKU-B: 0 layers, remaining 2 units valued at wacUnitCents (2550) = 5100.
        // LIFO for SKU-B: 0 layers, remaining 2 units valued at wacUnitCents (2550) = 5100.

        // Totals:
        // WAC = 16500 + 5100 = 21600.
        // FIFO = 17000 + 5100 = 22100.
        // LIFO = 16000 + 5100 = 21100.
        $this->assertEquals(21600, $data['total_valuation_wac_cents']);
        $this->assertEquals(22100, $data['total_valuation_fifo_cents']);
        $this->assertEquals(21100, $data['total_valuation_lifo_cents']);

        // Location valuations breakdown:
        // LOC-1:
        //   prod-1: 10 * 1100 = 11000
        //   prod-2: 2 * 2550 = 5100
        //   Total LOC-1 = 16100
        // LOC-2:
        //   prod-1: 5 * 1100 = 5500
        //   Total LOC-2 = 5500
        $locMap = [];
        foreach ($data['valuation_by_location'] as $locVal) {
            $locMap[$locVal['location_id']] = $locVal;
        }
        $this->assertEquals('Main Warehouse', $locMap['LOC-1']['name']);
        $this->assertEquals(16100, $locMap['LOC-1']['valuation']);
        $this->assertEquals('Storefront', $locMap['LOC-2']['name']);
        $this->assertEquals(5500, $locMap['LOC-2']['valuation']);

        // Recent activity check:
        $this->assertCount(2, $data['recent_activity']);
        $this->assertEquals('tx-2', $data['recent_activity'][0]['id']);
        $this->assertEquals('Product B', $data['recent_activity'][0]['product_name']);
        $this->assertEquals('tx-1', $data['recent_activity'][1]['id']);
        $this->assertEquals('Product A', $data['recent_activity'][1]['product_name']);
    }

    public function test_valuation_uses_default_wac_cents_when_no_layers_and_no_catalog_variant(): void
    {
        $tenantId = 'tenant-empty';
        $_SERVER['auth.tenant_id'] = $tenantId;

        DB::table('products')->insert([
            'id' => 'prod-default',
            'tenant_id' => $tenantId,
            'sku' => 'SKU-DEF',
            'name' => 'Default Product',
            'department' => 'GEN',
            'reorder_threshold' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        DB::table('product_locations')->insert([
            'product_id' => 'prod-default',
            'location_id' => 'LOC-DEF',
            'stock_quantity' => 4,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // No layers, no catalog variant -> default 1000 cents ($10.00)
        $response = $this->controller->valuation($this->requestMock, $tenantId);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // 4 units * 1000 cents = 4000 cents
        $this->assertEquals(4, $data['total_items_count']);
        $this->assertEquals(4000, $data['total_valuation_wac_cents']);
        $this->assertEquals(4000, $data['total_valuation_fifo_cents']);
        $this->assertEquals(4000, $data['total_valuation_lifo_cents']);
    }

    public function test_valuation_handles_stock_exceeding_cost_layers(): void
    {
        $tenantId = 'tenant-exceed';
        $_SERVER['auth.tenant_id'] = $tenantId;

        DB::table('products')->insert([
            'id' => 'prod-exceed',
            'tenant_id' => $tenantId,
            'sku' => 'SKU-EXCEED',
            'name' => 'Exceed Product',
            'department' => 'GEN',
            'reorder_threshold' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        DB::table('product_locations')->insert([
            'product_id' => 'prod-exceed',
            'location_id' => 'LOC-1',
            'stock_quantity' => 10,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Only 5 units in cost layer @ 2000 cents
        DB::table('inventory_cost_layers')->insert([
            'id' => 'layer-exceed',
            'tenant_id' => $tenantId,
            'variant_id' => 'SKU-EXCEED',
            'original_quantity' => 5,
            'remaining_quantity' => 5,
            'unit_cost_cents' => 2000,
            'received_at' => '2026-01-01 00:00:00'
        ]);

        // Total stock = 10, layers = 5 @ 2000 cents. WAC = 2000.
        // FIFO: 5 * 2000 + remaining 5 * WAC (2000) = 20000.
        // LIFO: 5 * 2000 + remaining 5 * WAC (2000) = 20000.
        $response = $this->controller->valuation($this->requestMock, $tenantId);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(10, $data['total_items_count']);
        $this->assertEquals(20000, $data['total_valuation_wac_cents']);
        $this->assertEquals(20000, $data['total_valuation_fifo_cents']);
        $this->assertEquals(20000, $data['total_valuation_lifo_cents']);
    }

    public function test_valuation_returns_500_on_database_error(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-err';

        // Drop table to induce database exception
        DB::schema()->dropIfExists('products');

        ob_start();
        $response = $this->controller->valuation($this->requestMock, 'tenant-err');
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
