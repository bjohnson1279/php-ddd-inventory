<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap database capsule
$capsule = require_once __DIR__ . '/../src/Infrastructure/Persistence/bootstrap_database.php';

use Illuminate\Database\Capsule\Manager as DB;
use InventoryApp\Infrastructure\ServiceContainer;

$once = in_array('--once', $argv);

echo "Starting DDD Queue Worker...\n";

do {
    $job = null;
    try {
        // 1. Transactional fetch and lock a job
        DB::transaction(function() use (&$job) {
            $job = DB::table('queued_jobs')
                ->whereNull('reserved_at')
                ->where('available_at', '<=', date('Y-m-d H:i:s'))
                ->orderBy('available_at', 'asc')
                ->lockForUpdate()
                ->first();

            if ($job) {
                $job->attempts = $job->attempts + 1;
                DB::table('queued_jobs')
                    ->where('id', $job->id)
                    ->update([
                        'reserved_at' => date('Y-m-d H:i:s'),
                        'attempts'    => $job->attempts
                    ]);
            }
        });
    } catch (\Throwable $txEx) {
        echo "Database lock failed: " . $txEx->getMessage() . "\n";
        usleep(500000); // Wait 500ms before retrying
        continue;
    }

    if (!$job) {
        if ($once) {
            echo "No jobs found. Exiting.\n";
            break;
        }
        // Sleep for 1 second and poll again
        usleep(1000000); // 1s
        continue;
    }

    echo "Processing Job ID: {$job->id} (Listener: {$job->listener_class}, Tenant: {$job->tenant_id})...\n";

    try {
        if (getenv('DB_CONNECTION') === 'pgsql' || getenv('DB_CONNECTION') === '') {
            DB::statement("SET app.current_tenant_id = '{$job->tenant_id}'");
        }

        // Reconstruct event object from serialized data
        $event = unserialize(base64_decode($job->event_data));

        // Resolve listener dependencies (e.g. SyncStockToShopify, NotificationListener)
        $listener = resolveListener($job->listener_class, $job->tenant_id);

        if ($listener) {
            $listener->handle($event);
            echo "Job completed successfully.\n";
        } else {
            throw new Exception("Unable to resolve listener: {$job->listener_class}");
        }

        // Remove job on success
        DB::table('queued_jobs')->where('id', $job->id)->delete();

    } catch (\Throwable $e) {
        echo "Job failed: " . $e->getMessage() . "\n";

        // Check attempts
        if ($job->attempts >= 5) {
            DB::table('queued_jobs')->where('id', $job->id)->delete();
            echo "Max attempts (5) reached. Job deleted.\n";
        } else {
            // Release job back to queue with an exponential backoff
            $retryDelay = 30 * $job->attempts; // 30s, 60s, 90s, 120s
            DB::table('queued_jobs')
                ->where('id', $job->id)
                ->update([
                    'reserved_at'  => null,
                    'available_at' => date('Y-m-d H:i:s', time() + $retryDelay),
                ]);
            echo "Released job for retry in {$retryDelay}s.\n";
        }
    }

} while (!$once);

function resolveListener(string $class, string $tenantId): ?object
{
    // Resolve SyncStockToShopify
    if ($class === \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::class) {
        $syncClient = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync(
            getenv('SHOPIFY_STORE_DOMAIN') ?: '',
            getenv('SHOPIFY_ACCESS_TOKEN') ?: ''
        );
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository();
        $productRepo = ServiceContainer::productRepo($tenantId);
        return new \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify($syncClient, $mappingRepo, $productRepo);
    }

    // Resolve SyncCatalogToShopify
    if ($class === \InventoryApp\Application\Catalog\Listeners\SyncCatalogToShopify::class) {
        $syncClient = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync(
            getenv('SHOPIFY_STORE_DOMAIN') ?: '',
            getenv('SHOPIFY_ACCESS_TOKEN') ?: ''
        );
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository();
        return new \InventoryApp\Application\Catalog\Listeners\SyncCatalogToShopify($syncClient, $mappingRepo);
    }

    // Resolve SyncJournalToQuickBooks
    if ($class === \InventoryApp\Application\Accounting\Listeners\SyncJournalToQuickBooks::class) {
        $syncClient = new \InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksJournalSync(
            getenv('QUICKBOOKS_COMPANY_ID') ?: 'mock-company',
            getenv('QUICKBOOKS_ACCESS_TOKEN') ?: 'mock-token'
        );
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksMappingRepository();
        return new \InventoryApp\Application\Accounting\Listeners\SyncJournalToQuickBooks($syncClient, $mappingRepo);
    }

    // Resolve SyncJournalToXero
    if ($class === \InventoryApp\Application\Accounting\Listeners\SyncJournalToXero::class) {
        $syncClient = new \InventoryApp\Infrastructure\Integration\Xero\XeroJournalSync(
            getenv('XERO_TENANT_ID') ?: 'mock-tenant',
            getenv('XERO_ACCESS_TOKEN') ?: 'mock-token'
        );
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\Xero\XeroMappingRepository();
        return new \InventoryApp\Application\Accounting\Listeners\SyncJournalToXero($syncClient, $mappingRepo);
    }

    // Resolve SyncJournalToNetSuite
    if ($class === \InventoryApp\Application\Accounting\Listeners\SyncJournalToNetSuite::class) {
        $syncClient = new \InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteJournalSync(
            getenv('NETSUITE_ACCOUNT_ID') ?: 'mock-account',
            getenv('NETSUITE_TOKEN') ?: 'mock-token'
        );
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteMappingRepository();
        return new \InventoryApp\Application\Accounting\Listeners\SyncJournalToNetSuite($syncClient, $mappingRepo);
    }

    // Resolve NotificationListener
    if ($class === \InventoryApp\Application\Notification\Listeners\NotificationListener::class) {
        $notificationService = new \InventoryApp\Application\Notification\Services\NotificationService();
        return new \InventoryApp\Application\Notification\Listeners\NotificationListener($notificationService);
    }

    // Fallback instantiation for parameterless constructors (like CreateCostLayerListener)
    if (class_exists($class)) {
        try {
            return new $class();
        } catch (\Throwable $e) {
            return null;
        }
    }

    return null;
}
