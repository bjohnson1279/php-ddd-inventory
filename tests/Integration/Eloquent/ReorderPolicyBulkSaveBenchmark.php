<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentReorderPolicyRepository;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Illuminate\Database\Capsule\Manager as Capsule;
use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ReorderPolicyBulkSaveBenchmark extends TestCase
{
    public function testSaveAllPerformance(): void
    {
        $repo = new EloquentReorderPolicyRepository();

        $policies = [];
        for ($i = 0; $i < 100; $i++) {
            $policies[] = new ReorderPolicy(
                Uuid::uuid4()->toString(),
                new SKU("BENCH-SKU-{$i}"),
                'LOC-BENCH',
                10,
                50,
                5,
                true
            );
        }

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $startIndividual = microtime(true);
        foreach ($policies as $policy) {
            $repo->save($policy);
        }
        $endIndividual = microtime(true);

        $individualQueries = Capsule::connection()->getQueryLog();
        $individualQueryCount = count($individualQueries);
        $individualTime = $endIndividual - $startIndividual;

        // Reset DB table
        Capsule::table('reorder_policies')->delete();

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $startBulk = microtime(true);
        $repo->saveAll($policies);
        $endBulk = microtime(true);

        $bulkQueries = Capsule::connection()->getQueryLog();
        $bulkQueryCount = count($bulkQueries);
        $bulkTime = $endBulk - $startBulk;

        echo "\n--- ReorderPolicy Bulk Save Benchmark (100 policies) ---\n";
        echo "Individual save() execution time: " . number_format($individualTime, 6) . "s, queries executed: " . $individualQueryCount . "\n";
        echo "Bulk saveAll()    execution time: " . number_format($bulkTime, 6) . "s, queries executed: " . $bulkQueryCount . "\n";

        $this->assertEquals(200, $individualQueryCount);
        $this->assertEquals(1, $bulkQueryCount);
        $this->assertLessThan($individualTime, $bulkTime);
    }
}
