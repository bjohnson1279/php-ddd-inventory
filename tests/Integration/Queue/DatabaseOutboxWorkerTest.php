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

        $env = sprintf(
            'DB_CONNECTION=%s DB_HOST=%s DB_PORT=%s DB_DATABASE=%s DB_USERNAME=%s DB_PASSWORD=%s',
            escapeshellarg(DB::connection()->getDriverName()),
            escapeshellarg((string)getenv('DB_HOST')),
            escapeshellarg((string)getenv('DB_PORT')),
            escapeshellarg((string)getenv('DB_DATABASE')),
            escapeshellarg((string)getenv('DB_USERNAME')),
            escapeshellarg((string)getenv('DB_PASSWORD'))
        );
        $cmd = "$env php scripts/outbox-worker.php --once";
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



use Illuminate\Database\Capsule\Manager as Capsule;



{
    {
        Capsule::table('queued_jobs')->delete();
        Capsule::table('outbox_events')->delete();
        }

    }

    {



        $baseDir = realpath(__DIR__ . "/../../..");
                $extDir = ini_get('extension_dir') ?: 'C:\\Users\\johns\\AppData\\Local\\Microsoft\\WinGet\\Packages\\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\\ext';
        $phpExec = PHP_BINARY . ' -d extension_dir=' . escapeshellarg($extDir) . ' -d extension=mbstring -d extension=pdo_sqlite';
        $dbFile = getenv('DB_DATABASE') ?: ($baseDir . '/storage/data/test.sqlite');
        $cmd = (PHP_OS_FAMILY === 'Windows')
            ? "set DB_CONNECTION=sqlite&& set DB_DATABASE={$dbFile}&& " . $phpExec . " " . $baseDir . "/scripts/" . (str_contains(__FILE__, 'Outbox') ? "outbox-worker.php" : "queue-worker.php") . " --once"
            : "DB_CONNECTION=sqlite DB_DATABASE=" . escapeshellarg($dbFile) . " " . $phpExec . " " . $baseDir . "/scripts/" . (str_contains(__FILE__, 'Outbox') ? "outbox-worker.php" : "queue-worker.php") . " --once";
        DB::disconnect();
        DB::reconnect();



    }
}





{
    {
        }

    }

    {



        $cmd = "php scripts/outbox-worker.php --once";



    }
}
