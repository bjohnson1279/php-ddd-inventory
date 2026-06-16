<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$capsule = new Illuminate\Database\Capsule\Manager;
$capsule->addConnection([
    'driver'   => getenv('DB_CONNECTION') ?: 'pgsql',
    'host'     => getenv('DB_HOST')       ?: 'localhost',
    'database' => getenv('DB_DATABASE')   ?: 'ddd_inventory',
    'username' => getenv('DB_USERNAME')   ?: 'ddd_user',
    'password' => getenv('DB_PASSWORD')   ?: 'secret',
    'port'     => getenv('DB_PORT')       ?: 5432,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "Products:\n";
foreach (Illuminate\Database\Capsule\Manager::table('products')->get() as $p) {
    echo "ID: {$p->id}, Tenant: {$p->tenant_id}, SKU: {$p->sku}, Name: {$p->name}\n";
}
echo "\nKits:\n";
foreach (Illuminate\Database\Capsule\Manager::table('kits')->get() as $k) {
    echo "ID: {$k->id}, SKU: {$k->sku}, Name: {$k->name}\n";
}
