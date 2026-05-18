<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

$capsule = new Capsule;
$capsule->addConnection([
    'driver' => getenv('DB_CONNECTION') ?: 'pgsql',
    'host' => getenv('DB_HOST') ?: 'db',
    'database' => getenv('DB_DATABASE') ?: 'ddd_inventory',
    'username' => getenv('DB_USERNAME') ?: 'ddd_user',
    'password' => getenv('DB_PASSWORD') ?: 'secret',
    'port' => getenv('DB_PORT') ?: 5432,
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Clean tables before each integration run
$connection = $capsule->getConnection();
$connection->statement('TRUNCATE TABLE inventory_transactions, product_locations, products, inventory_count_items, inventory_counts, ledger_entries, serialized_items, barcodes, stock_onboarding_items, stock_onboardings, journal_entries RESTART IDENTITY CASCADE');

// Ensure standard locations exist
$connection->table('locations')->insertOrIgnore([
    ['id' => 'LOC-INT', 'name' => 'Integration Location', 'type' => 'TEST']
]);
