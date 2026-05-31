<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap database
$capsule = require_once __DIR__ . '/../src/Infrastructure/Persistence/bootstrap_database.php';

use Illuminate\Database\Capsule\Manager as DB;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;

echo "Starting Shopify Inventory Sync Retry Worker...\n";

// Initialize Shopify Sync service
$syncClient = new ShopifyInventorySync(
    getenv('SHOPIFY_STORE_DOMAIN') ?: '',
    getenv('SHOPIFY_ACCESS_TOKEN') ?: ''
);
$mappingRepo = new ShopifyMappingRepository();

// Get all pending sync failures
$failures = DB::table('shopify_sync_failures')
    ->where('status', 'pending')
    ->get();

if ($failures->isEmpty()) {
    echo "No pending sync failures to process.\n";
    exit(0);
}

echo "Found " . $failures->count() . " pending sync failures. Retrying...\n";

foreach ($failures as $f) {
    echo "Retrying SKU: {$f->sku} (Location: {$f->location_id}, Attempt: " . ($f->attempts + 1) . ")...\n";
    
    // Look up Shopify mappings
    $shopifyInventoryItemId = $mappingRepo->findShopifyInventoryItemId($f->sku);
    $shopifyLocationId      = $mappingRepo->findShopifyLocationId($f->location_id);
    
    if (!$shopifyInventoryItemId || !$shopifyLocationId) {
        echo "Mapping missing for SKU {$f->sku} or Location {$f->location_id}. Skipping.\n";
        continue;
    }

    try {
        // Attempt sync
        $syncClient->setInventoryLevel($shopifyInventoryItemId, $shopifyLocationId, $f->quantity);
        
        // Success
        DB::table('shopify_sync_failures')
            ->where('id', $f->id)
            ->update([
                'status'     => 'success',
                'attempts'   => $f->attempts + 1,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        echo "Success syncing SKU: {$f->sku}\n";
    } catch (\Throwable $e) {
        echo "Failed to sync: " . $e->getMessage() . "\n";
        
        $newAttempts = $f->attempts + 1;
        $status = $newAttempts >= 5 ? 'failed' : 'pending';
        
        DB::table('shopify_sync_failures')
            ->where('id', $f->id)
            ->update([
                'attempts'   => $newAttempts,
                'last_error' => $e->getMessage(),
                'status'     => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }
}

echo "Shopify Sync Retry Worker finished.\n";
