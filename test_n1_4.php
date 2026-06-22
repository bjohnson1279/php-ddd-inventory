<?php

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Infrastructure/Persistence/bootstrap_database.php';

use InventoryApp\Infrastructure\ServiceContainer;
use InventoryApp\Application\Inventory\UseCases\ProcessSaleBatch;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Entities\LocationStock;

\Illuminate\Database\Capsule\Manager::connection()->enableQueryLog();

$repo = ServiceContainer::productRepo('system');

// Create test products
$p1 = new Product(uniqid(), new SKU('TSHIRT-L-RED'), 'Test 1', new Department('Electronics'), new Quantity(10), 1, 100, 0.1);
$p1->loadLocationStock(new LocationStock(new LocationId('LOC-STOREFRONT'), new Quantity(50), new Quantity(0), new Quantity(0), new Quantity(0), new Quantity(0)));
$repo->saveAll([$p1]);

$p2 = new Product(uniqid(), new SKU('PANTS-M-BLK'), 'Test 2', new Department('Electronics'), new Quantity(10), 1, 100, 0.1);
$p2->loadLocationStock(new LocationStock(new LocationId('LOC-STOREFRONT'), new Quantity(50), new Quantity(0), new Quantity(0), new Quantity(0), new Quantity(0)));
$repo->saveAll([$p2]);

// Setup mappings
\Illuminate\Database\Capsule\Manager::table('shopify_sku_mappings')->updateOrInsert(['sku' => 'TSHIRT-L-RED'], ['shopify_inventory_item_id' => 'SHOPIFY-INV-1']);
\Illuminate\Database\Capsule\Manager::table('shopify_sku_mappings')->updateOrInsert(['sku' => 'PANTS-M-BLK'], ['shopify_inventory_item_id' => 'SHOPIFY-INV-2']);

\Illuminate\Database\Capsule\Manager::table('shopify_location_mappings')->updateOrInsert(
    ['shopify_location_id' => 'SHOPIFY-LOC-1'],
    ['our_location_id' => 'LOC-STOREFRONT']
);

\Illuminate\Database\Capsule\Manager::connection()->flushQueryLog();

$sync = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync('test', 'test');
$mappings = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository();
$listener = new \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify($sync, $mappings, clone $repo);
ServiceContainer::dispatcher()->subscribe(\InventoryApp\Domain\Inventory\Events\SaleProcessed::class, [$listener, 'handle']);

$processSaleBatch = new ProcessSaleBatch($repo, ServiceContainer::dispatcher());

$processSaleBatch->execute([
    ['sku' => 'TSHIRT-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1],
    ['sku' => 'PANTS-M-BLK', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
]);

$logs = \Illuminate\Database\Capsule\Manager::connection()->getQueryLog();

echo "Queries executed after setup: " . count($logs) . "\n";
foreach ($logs as $log) {
    if (strpos($log['query'], 'shopify') !== false) {
        echo $log['query'] . " | bindings: " . json_encode($log['bindings']) . "\n";
    }
}
