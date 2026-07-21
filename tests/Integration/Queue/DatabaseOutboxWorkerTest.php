<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use InventoryApp\Infrastructure\ServiceContainer;
use DateTimeImmutable;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class DatabaseOutboxWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean up outbox events to ensure run-to-run isolation
        if (getenv('DB_CONNECTION') === 'sqlite' || DB::connection()->getDriverName() === 'sqlite') {
            require_once __DIR__ . '/../../../src/Infrastructure/Persistence/sqlite_setup.php';
            \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema(DB::connection());
            DB::table('outbox_events')->delete();
        } else {
            DB::table('outbox_events')->truncate();
        }

        ServiceContainer::resetDispatcher();
    }

    public function testOutboxWorkerProcessesPendingEvents(): void
    {
        $repo = ServiceContainer::outboxRepo();

        // 1. Save outbox event
        $repo->save([
            'eventName' => 'TestOutboxEvent',
            'occurredOn' => new DateTimeImmutable(),
            'sku' => 'OUTBOX-TEST-SKU',
            'quantity' => 10
        ]);

        // 2. Verify it is pending in DB
        $this->assertEquals(1, DB::table('outbox_events')->count());
        $pendingCount = DB::table('outbox_events')->whereNull('processed_at')->count();
        $this->assertEquals(1, $pendingCount);

        // 3. Run outbox-worker.php CLI script with --once flag
        $output = [];
        $resultCode = -1;
        $cmd = "php scripts/outbox-worker.php --once";
        exec($cmd, $output, $resultCode);

        // 4. Verify script finished successfully
        $this->assertEquals(0, $resultCode, "outbox-worker.php exited with code {$resultCode}");

        // 5. Verify event is now processed in DB
        $processedCount = DB::table('outbox_events')->whereNotNull('processed_at')->count();
        $this->assertEquals(1, $processedCount);

        $pendingCountAfter = DB::table('outbox_events')->whereNull('processed_at')->count();
        $this->assertEquals(0, $pendingCountAfter);
    }
}





{
    {
        }

    }

    {






    }
}
