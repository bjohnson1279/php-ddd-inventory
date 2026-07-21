<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductUomConfigurationRepository;
use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;
use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Enums\UomCategory;
use InventoryApp\Domain\Uom\Services\StandardUnits;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentProductUomConfigurationRepositoryBenchmark extends TestCase
{
    public function testSavePerformance(): void
    {
        $repo = new EloquentProductUomConfigurationRepository();

        $configId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $variantId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $baseUnit = StandardUnits::each();

        $config = new ProductUomConfiguration($configId, $variantId, $baseUnit);

        // Add 100 conversion rules
        for ($i = 2; $i < 102; $i++) {
            $unit = new UnitOfMeasure('Unit'.$i, 'u'.$i, UomCategory::Discrete);
            $config->addConversionRule($unit, (float)($i), 'Label '.$i);
        }

        Capsule::connection()->enableQueryLog();

        $start = microtime(true);
        $repo->save($config);
        $end = microtime(true);

        $queries = Capsule::connection()->getQueryLog();

        echo "\nSave time for 100 rules: " . ($end - $start) . " seconds\n";
        echo "Queries executed: " . count($queries) . "\n";

        $this->assertTrue(count($queries) < 10);
    }
}
