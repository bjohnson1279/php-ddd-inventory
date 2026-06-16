<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

$driver = getenv('DB_CONNECTION') ?: 'pgsql';

$capsule = new Capsule;

if ($driver === 'sqlite') {
    $dbPath = getenv('DB_DATABASE') ?: ':memory:';
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

if ($driver !== 'sqlite') {
    try {
        $hasAllocated = false;
        $columns = $connection->select("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'product_locations' 
              AND column_name = 'allocated_quantity'
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
        'tenants',
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
        'reorder_policies'
    ];
    
    foreach ($tables as $t) {
        $connection->statement("DELETE FROM {$t}");
    }
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
        tenants,
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
        reorder_policies
    RESTART IDENTITY CASCADE');
}

// Ensure standard locations exist
$connection->table('locations')->insertOrIgnore([
    ['id' => 'LOC-INT', 'name' => 'Integration Location', 'type' => 'TEST']
]);

// Ensure standard test tenant exists
$connection->table('tenants')->insertOrIgnore([
    ['id' => 'test-tenant', 'name' => 'Test Tenant']
]);

// Ensure standard roles exist
$connection->table('roles')->insertOrIgnore([
    ['id' => 'admin',   'name' => 'Administrator'],
    ['id' => 'manager', 'name' => 'Manager'],
    ['id' => 'staff',   'name' => 'Staff']
]);

function uuidv4(): string {
    return \Ramsey\Uuid\Uuid::uuid4()->toString();
}
