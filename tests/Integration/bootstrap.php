<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

$capsule = new Capsule;
$capsule->addConnection([
    'driver'   => getenv('DB_CONNECTION') ?: 'pgsql',
    'host'     => getenv('DB_HOST')       ?: 'localhost',
    'database' => getenv('DB_DATABASE')   ?: 'ddd_inventory',
    'username' => getenv('DB_USERNAME')   ?: 'ddd_user',
    'password' => getenv('DB_PASSWORD')   ?: 'secret',
    'port'     => getenv('DB_PORT')       ?: 5432,
    'charset'  => 'utf8',
    'prefix'   => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Clean tables before each integration run
$connection = $capsule->getConnection();
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
    product_uom_configurations,
    uom_conversion_rules,
    kits,
    kit_components,
    roles,
    user_roles,
    role_permissions
RESTART IDENTITY CASCADE');

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
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
