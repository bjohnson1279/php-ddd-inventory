<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Infrastructure\ServiceContainer;
use DateTimeImmutable;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class DatabaseQueueTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean up queued jobs and mapping/catalog tables to ensure run-to-run isolation
        if (getenv('DB_CONNECTION') === 'sqlite' || DB::connection()->getDriverName() === 'sqlite') {
            require_once __DIR__ . '/../../../src/Infrastructure/Persistence/sqlite_setup.php';
            \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema(DB::connection());
            DB::table('queued_jobs')->delete();
            DB::table('shopify_sync_failures')->delete();
        } else {
            DB::table('queued_jobs')->truncate();
            DB::table('shopify_sync_failures')->truncate();
        }

        DB::table('shopify_sku_mappings')->delete();
        DB::table('catalog_variants')->delete();
        DB::table('catalog_products')->delete();
        DB::table('quickbooks_journal_mappings')->delete();
        DB::table('xero_journal_mappings')->delete();
        DB::table('netsuite_journal_mappings')->delete();
        DB::table('journal_entries')->delete();

        DB::table('tenants')->insertOrIgnore([
            ['id' => 'test-tenant', 'name' => 'Test Tenant']
        ]);
        DB::table('locations')->insertOrIgnore([
            ['id' => 'LOC-INT', 'name' => 'Integration Location', 'type' => 'TEST']

        ServiceContainer::resetDispatcher();
    }

    public function testEventDispatchQueuesListenerAndWorkerProcessesIt(): void
    {
        $tenantId = 'test-tenant';
        $skuStr = 'QUEUE-TEST-SKU';
        $locationStr = 'LOC-INT';

        // 1. Seed Product
        DB::table('tenants')->insertOrIgnore(['id' => 'test-tenant', 'name' => 'Test Tenant']);
        DB::table('products')->insertOrIgnore([
            'id'                => uuidv4(),
            'tenant_id'         => $tenantId,
            'sku'               => $skuStr,
            'name'              => 'Queue Test Product',
            'department'        => 'GEN',
            'reorder_threshold' => 5,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s')

        // Seed product location stock (10 units)
        DB::table('product_locations')->insertOrIgnore([
            'product_id'        => DB::table('products')->where('sku', $skuStr)->value('id'),
            'location_id'       => $locationStr,
            'stock_quantity'    => 10,
            'open_box_quantity' => 0,
            'damaged_quantity'  => 0,

        // Seed catalog product & variant to satisfy foreign key constraint on shopify_sku_mappings
        $catalogProductId = uuidv4();
        DB::table('catalog_products')->insertOrIgnore([
            'id'          => $catalogProductId,
            'name'        => 'Queue Test Product',
            'department'  => 'GEN',
            'created_at'  => date('Y-m-d H:i:s')
        DB::table('catalog_variants')->insertOrIgnore([
            'id'          => uuidv4(),
            'product_id'  => $catalogProductId,
            'sku'         => $skuStr,
            'price'       => 10.00,
            'attributes'  => '{}',

        // 2. Seed Shopify mappings so the Shopify sync listener tries to execute (otherwise it returns early)
        DB::table('shopify_sku_mappings')->insertOrIgnore([
            'id'                        => uuidv4(),
            'sku'                       => $skuStr,
            'shopify_inventory_item_id' => 'shp-inv-123',
            'created_at'                => date('Y-m-d H:i:s')

        DB::table('shopify_location_mappings')->insertOrIgnore([
            'id'                  => uuidv4(),
            'our_location_id'     => $locationStr,
            'shopify_location_id' => 'shp-loc-456',
            'created_at'          => date('Y-m-d H:i:s')

        // Verify queue is empty
        $this->assertEquals(0, DB::table('queued_jobs')->count());

        // 3. Register the queued listener on the dispatcher
        $syncClient = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyInventorySync(
            'example.myshopify.com',
            'token'
        );
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository();
        $shopifyListener = new \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify(
            $syncClient,
            $mappingRepo,
            ServiceContainer::productRepo($tenantId)

        $dispatcher = ServiceContainer::dispatcher();
        $dispatcher->subscribe(StockReceived::class, [$shopifyListener, 'handle']);

        $event = new StockReceived(
            new SKU($skuStr),
            new LocationId($locationStr),
            5,
            'PO-QUEUE',
            new DateTimeImmutable()

        $dispatcher->dispatch($event);

        // 4. Verify job is queued in the database table
        $this->assertEquals(1, DB::table('queued_jobs')->count());
        $job = DB::table('queued_jobs')->first();
        $this->assertEquals('InventoryApp\Application\Inventory\Listeners\SyncStockToShopify', $job->listener_class);
        $this->assertNotNull($job->event_data);

        // 5. Run the queue worker CLI script with --once flag via PHP exec to verify it works as a process
        $output = [];
        $resultCode = -1;

        // Run CLI command using the local php installation
        $cmd = "php scripts/queue-worker.php --once";
        exec($cmd, $output, $resultCode);

        // 6. Verify worker exited with status code 0 and processed the job
        $this->assertEquals(0, $resultCode, implode("\n", $output));

        // Verify job was removed from queue on completion/failure processing

        $logString = implode("\n", $output);
        $this->assertStringContainsString("Processing Job ID", $logString);
        $this->assertStringContainsString("SyncStockToShopify", $logString);
    }

    public function testCatalogVariantAddedQueuesShopifyCatalogSync(): void
    {
        $productId = uuidv4();
        $skuStr = 'CAT-QUEUE-SKU';

        // Seed catalog product
        DB::table('catalog_products')->insert([
            'id'          => $productId,
            'name'        => 'Outbound Sync Catalog Product',
            'description' => 'A test catalog product',

        // Register SyncCatalogToShopify listener on the dispatcher
            'mock.shopify.com',
        $catalogSyncListener = new \InventoryApp\Application\Catalog\Listeners\SyncCatalogToShopify($syncClient, $mappingRepo);

        $dispatcher->subscribe(\InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog::class, [$catalogSyncListener, 'handle']);


        // Execute AddVariant usecase to trigger event
        $catalogProductRepo = ServiceContainer::catalogProductRepo();
        $addVariantUseCase = new \InventoryApp\Application\Catalog\UseCases\AddVariant($catalogProductRepo, $dispatcher);

        $addVariantUseCase->execute(
            $productId,
            uuidv4(),
            $skuStr,
            ['color' => 'blue'],
            25.50

        // Verify job is queued in the database table
        $this->assertEquals('InventoryApp\Application\Catalog\Listeners\SyncCatalogToShopify', $job->listener_class);

        // Run the queue worker to process the job

        // Verify worker completed successfully and job is deleted from queue

        // Verify the mapping was successfully created in the database mapping table!
        $this->assertEquals('mock-inv-item-' . $skuStr, $mappingRepo->findShopifyInventoryItemId($skuStr));
    }

    public function testJournalEntryRecordedQueuesQuickBooksSync(): void
    {
        $entryId = uuidv4();

        // 1. Register SyncJournalToQuickBooks listener on the dispatcher
        $syncClient = new \InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksJournalSync(
            'mock-company',
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksMappingRepository();
        $qboSyncListener = new \InventoryApp\Application\Accounting\Listeners\SyncJournalToQuickBooks($syncClient, $mappingRepo);

        $dispatcher->subscribe(\InventoryApp\Domain\Accounting\Events\JournalEntryRecorded::class, [$qboSyncListener, 'handle']);


        // 2. Instantiate and save a journal entry using the repository to trigger the event
        $journalRepo = ServiceContainer::journalRepo();
        $entry = new \InventoryApp\Domain\Accounting\Aggregates\JournalEntry(
            $entryId,
            $tenantId,
            new \DateTimeImmutable(),
            'Integration Test Journal Entry to QBO',
            'ref-qbo-123',
            \InventoryApp\Domain\Accounting\Enums\AccountingMethod::Accrual
        $entry->addLine(\InventoryApp\Domain\Accounting\ValueObjects\AccountCode::cash(), 5000, \InventoryApp\Domain\Accounting\Enums\DebitCredit::Debit, 'Deposit');
        $entry->addLine(\InventoryApp\Domain\Accounting\ValueObjects\AccountCode::salesRevenue(), 5000, \InventoryApp\Domain\Accounting\Enums\DebitCredit::Credit, 'Revenue');
        $entry->assertBalanced();

        $journalRepo->save($entry);

        // 3. Verify job is queued in the database table
        $this->assertEquals('InventoryApp\Application\Accounting\Listeners\SyncJournalToQuickBooks', $job->listener_class);

        // 4. Run the queue worker to process the job


        // 5. Verify the mapping was successfully created in the mapping table
        $this->assertNotNull($mappingRepo->findQuickBooksJournalId($entryId));
        $this->assertStringContainsString('mock-qbo-journal-', $mappingRepo->findQuickBooksJournalId($entryId));
    }

    public function testJournalEntryRecordedQueuesXeroSync(): void
    {

        $syncClient = new \InventoryApp\Infrastructure\Integration\Xero\XeroJournalSync(
            'mock-tenant',
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\Xero\XeroMappingRepository();
        $xeroSyncListener = new \InventoryApp\Application\Accounting\Listeners\SyncJournalToXero($syncClient, $mappingRepo);

        $dispatcher->subscribe(\InventoryApp\Domain\Accounting\Events\JournalEntryRecorded::class, [$xeroSyncListener, 'handle']);


            'Integration Test Journal Entry to Xero',
            'ref-xero-123',
        $entry->addLine(\InventoryApp\Domain\Accounting\ValueObjects\AccountCode::cash(), 3000, \InventoryApp\Domain\Accounting\Enums\DebitCredit::Debit, 'Deposit');
        $entry->addLine(\InventoryApp\Domain\Accounting\ValueObjects\AccountCode::salesRevenue(), 3000, \InventoryApp\Domain\Accounting\Enums\DebitCredit::Credit, 'Revenue');


        $this->assertEquals('InventoryApp\Application\Accounting\Listeners\SyncJournalToXero', $job->listener_class);



        $this->assertNotNull($mappingRepo->findXeroJournalId($entryId));
        $this->assertStringContainsString('mock-xero-journal-', $mappingRepo->findXeroJournalId($entryId));
    }

    public function testJournalEntryRecordedQueuesNetSuiteSync(): void
    {

        $syncClient = new \InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteJournalSync(
            'mock-account',
        $mappingRepo = new \InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteMappingRepository();
        $nsSyncListener = new \InventoryApp\Application\Accounting\Listeners\SyncJournalToNetSuite($syncClient, $mappingRepo);

        $dispatcher->subscribe(\InventoryApp\Domain\Accounting\Events\JournalEntryRecorded::class, [$nsSyncListener, 'handle']);


            'Integration Test Journal Entry to NetSuite',
            'ref-ns-123',
        $entry->addLine(\InventoryApp\Domain\Accounting\ValueObjects\AccountCode::cash(), 4500, \InventoryApp\Domain\Accounting\Enums\DebitCredit::Debit, 'Deposit');
        $entry->addLine(\InventoryApp\Domain\Accounting\ValueObjects\AccountCode::salesRevenue(), 4500, \InventoryApp\Domain\Accounting\Enums\DebitCredit::Credit, 'Revenue');


        $this->assertEquals('InventoryApp\Application\Accounting\Listeners\SyncJournalToNetSuite', $job->listener_class);



        $this->assertNotNull($mappingRepo->findNetSuiteJournalId($entryId));
        $this->assertStringContainsString('mock-netsuite-journal-', $mappingRepo->findNetSuiteJournalId($entryId));
    }

    public function testQueueWorkerHandlesFailureAndBackoff(): void
    {
        $jobId = uuidv4();

        // 1. Insert a job that will fail to resolve
        DB::table('queued_jobs')->insert([
            'id'             => $jobId,
            'tenant_id'      => 'test-tenant',
            'listener_class' => 'NonExistentListenerClass',
            'event_data'     => base64_encode(serialize(new \stdClass())),
            'attempts'       => 0,
            'reserved_at'    => null,
            'available_at'   => date('Y-m-d H:i:s'),
            'created_at'     => date('Y-m-d H:i:s')


        // 2. Run queue worker

        // Verify exit code is 0 (failures are caught and job is released/deleted)

        // The job should still exist in the queue but with attempts incremented and reserved_at nullified
        $job = DB::table('queued_jobs')->where('id', $jobId)->first();
        $this->assertEquals(1, $job->attempts);
        $this->assertNull($job->reserved_at);

        // Check that available_at is set in the future (retry delay is 30 * attempts = 30 seconds)
        $availableAt = new \DateTime($job->available_at);
        $now = new \DateTime();
        $diff = $availableAt->getTimestamp() - $now->getTimestamp();
        $this->assertGreaterThanOrEqual(25, $diff); // Allow slight timing variations

        // 3. Now test that max attempts deleting works (set attempts to 5)
        DB::table('queued_jobs')->where('id', $jobId)->update([
            'attempts'     => 5,
            'available_at' => date('Y-m-d H:i:s') // Make it available again



        // Job should now be deleted because attempts reached 5
        $this->assertEquals(0, DB::table('queued_jobs')->where('id', $jobId)->count());
    }
}





{
    {
        }



    }

    {
















    }

    {










    }

    {









    }

    {









    }

    {









    }

    {










    }
}
