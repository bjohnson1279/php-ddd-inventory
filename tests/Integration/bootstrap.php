<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

$driver = getenv('DB_CONNECTION') ?: 'pgsql';

$capsule = new Capsule;

if ($driver === 'sqlite') {
    $dbPath = getenv('DB_DATABASE') ?: 'storage/data/test.sqlite';
    if ($dbPath !== ':memory:' && !str_starts_with($dbPath, '/') && !str_contains($dbPath, ':')) {
        $dbPath = __DIR__ . '/../../' . $dbPath;
    }
    $capsule->addConnection([
        'driver'   => 'sqlite',
        'database' => $dbPath,
        'prefix'   => '',
    ]);
} else {
    $capsule->addConnection([
        'driver'   => $driver,
        'host'     => getenv('DB_HOST')       ?: 'localhost',
        'database' => getenv('DB_DATABASE')   ?: 'ddd_inventory',
        'username' => getenv('DB_USERNAME')   ?: 'ddd_user',
        'password' => getenv('DB_PASSWORD')   ?: 'secret',
        'port'     => getenv('DB_PORT')       ?: 5432,
        'charset'  => 'utf8',
        'prefix'   => '',
    ]);
}

$capsule->setAsGlobal();
$capsule->bootEloquent();

$connection = $capsule->getConnection();

if ($driver === 'sqlite') {
    try {
        $connection->statement('PRAGMA busy_timeout=10000;');
    } catch (\Exception $e) {}
}

if ($driver !== 'sqlite') {
    try {
        $hasAllocated = false;
        $columns = $connection->select("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'product_locations' 
              AND column_name = 'allocated_quantity'
              AND table_schema = 'public'
              AND table_catalog = current_database()
        ");
        if (!empty($columns)) {
            $hasAllocated = true;
        }

        if (!$hasAllocated) {
            $connection->statement("
                ALTER TABLE product_locations 
                ADD COLUMN allocated_quantity INTEGER NOT NULL DEFAULT 0,
                ADD COLUMN in_transit_quantity INTEGER NOT NULL DEFAULT 0
            ");
        }

        $hasGrid = false;
        $gridColumns = $connection->select("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'warehouse_locations' 
              AND column_name = 'grid_x'
              AND table_schema = 'public'
              AND table_catalog = current_database()
        ");
        if (!empty($gridColumns)) {
            $hasGrid = true;
        }

        if (!$hasGrid) {
            $connection->statement("
                ALTER TABLE warehouse_locations 
                ADD COLUMN grid_x INTEGER NOT NULL DEFAULT 0,
                ADD COLUMN grid_y INTEGER NOT NULL DEFAULT 0,
                ADD COLUMN width INTEGER NOT NULL DEFAULT 1,
                ADD COLUMN height INTEGER NOT NULL DEFAULT 1
            ");
        }

        $connection->statement("
            CREATE TABLE IF NOT EXISTS demand_forecasts (
                id VARCHAR(50) PRIMARY KEY,
                sku VARCHAR(50) NOT NULL,
                location_id VARCHAR(50) NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
                forecasted_quantity INTEGER NOT NULL,
                period_start TIMESTAMP NOT NULL,
                period_end TIMESTAMP NOT NULL,
                confidence_level NUMERIC NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (sku, location_id, period_start, period_end)
            )
        ");
        $connection->statement("
            CREATE INDEX IF NOT EXISTS idx_demand_forecasts_sku_loc ON demand_forecasts(sku, location_id)
        ");

        $connection->statement("
            CREATE TABLE IF NOT EXISTS shipments (
                id VARCHAR(50) PRIMARY KEY,
                sku VARCHAR(50) NOT NULL,
                quantity INTEGER NOT NULL,
                destination_address TEXT NOT NULL,
                carrier VARCHAR(50) NOT NULL,
                tracking_number VARCHAR(100),
                label_url TEXT,
                shipping_rate_cents INTEGER NOT NULL,
                status VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $connection->statement("
            CREATE TABLE IF NOT EXISTS outbox_events (
                id VARCHAR(50) PRIMARY KEY,
                event_name VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                occurred_on TIMESTAMP NOT NULL,
                processed_at TIMESTAMP DEFAULT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_error TEXT DEFAULT NULL,
                next_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (\Exception $e) {
        // Ignore or log error
    }
}

if ($driver === 'sqlite') {
    require_once __DIR__ . '/../../src/Infrastructure/Persistence/sqlite_setup.php';
    \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema($connection);
    
    $tables = [
        'inventory_transactions', 
        'product_locations', 
        'products', 
        'inventory_count_items', 
        'inventory_counts', 
        'ledger_entries', 
        'serialized_items', 
        'barcodes', 
        'stock_onboarding_items', 
        'stock_onboardings', 
        'journal_entries', 
        'api_tokens', 
        'users', 
        'shopify_location_mappings',
        'shopify_sku_mappings',
        'shopify_sync_failures',
        'quickbooks_journal_mappings',
        'xero_journal_mappings',
        'netsuite_journal_mappings',
        'product_uom_configurations',
        'uom_conversion_rules',
        'kits',
        'kit_components',
        'roles',
        'user_roles',
        'role_permissions',
        'notifications',
        'inventory_cost_layers',
        'warehouse_locations',
        'purchase_orders',
        'purchase_order_items',
        'reorder_policies',
        'demand_forecasts',
        'shipments',
        'outbox_events',
        'compliance_ledgers'
    ];
    
    foreach ($tables as $t) {
        try {
            $connection->statement("DELETE FROM {$t}");
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }
    }

    $connection->table('tenants')->where('id', '!=', 'test-tenant')->delete();
} else {
    $connection->statement('TRUNCATE TABLE
        inventory_transactions, 
        product_locations, 
        products, 
        inventory_count_items, 
        inventory_counts, 
        ledger_entries, 
        serialized_items, 
        barcodes, 
        stock_onboarding_items, 
        stock_onboardings, 
        journal_entries, 
        api_tokens, 
        users, 
        shopify_location_mappings,
        shopify_sku_mappings,
        shopify_sync_failures,
        quickbooks_journal_mappings,
        xero_journal_mappings,
        netsuite_journal_mappings,
        product_uom_configurations,
        uom_conversion_rules,
        kits,
        kit_components,
        roles,
        user_roles,
        role_permissions,
        notifications,
        inventory_cost_layers,
        warehouse_locations,
        purchase_orders,
        purchase_order_items,
        reorder_policies,
        demand_forecasts,
        shipments,
        outbox_events,
        compliance_ledgers
    RESTART IDENTITY CASCADE');

    // Wipe all tenants except test-tenant
    $connection->table('tenants')->whereNotIn('id', ['test-tenant', 'system'])->delete();
}

// Ensure standard locations exist
$connection->table('locations')->insertOrIgnore([
    ['id' => 'LOC-INT', 'name' => 'Integration Location', 'type' => 'TEST']
]);

// Ensure standard test tenant exists
$connection->table('tenants')->upsert(
    [
        ['id' => 'test-tenant', 'name' => 'Test Tenant'],
        ['id' => 'system', 'name' => 'System Tenant']
    ],
    ['id'],
    ['name']
);

// Ensure standard roles exist
$connection->table('roles')->insertOrIgnore([
    ['id' => 'admin',   'name' => 'Administrator'],
    ['id' => 'manager', 'name' => 'Manager'],
    ['id' => 'staff',   'name' => 'Staff']
]);

function uuidv4(): string {
    return \Ramsey\Uuid\Uuid::uuid4()->toString();
}
