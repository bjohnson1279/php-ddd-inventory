<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductUomConfigurationRepository;
use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;
use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Enums\UomCategory;
use InventoryApp\Domain\Uom\Services\StandardUnits;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentProductUomConfigurationRepositoryTest extends TestCase
{
    public function testSaveAndRetrieveConfiguration(): void
    {
        $repo = new EloquentProductUomConfigurationRepository();

        $configId = uuidv4();
        $variantId = uuidv4();
        $baseUnit = StandardUnits::each();

        $config = new ProductUomConfiguration($configId, $variantId, $baseUnit);

        $caseUnit = new UnitOfMeasure('Case', 'cs', UomCategory::Discrete);
        $config->addConversionRule($caseUnit, 24.0, 'Case of 24');
        $config->setPurchaseUnit($caseUnit);

        $dozenUnit = StandardUnits::dozen();
        $config->addConversionRule($dozenUnit, 12.0, 'Dozen');
        $config->setSaleUnit($dozenUnit);

        // Save
        $repo->save($config);

        // Find by Variant
        $found = $repo->findByVariant($variantId);
        $this->assertNotNull($found);
        $this->assertEquals($configId, $found->id);
        $this->assertEquals($variantId, $found->variantId);
        $this->assertTrue($found->baseUnit()->equals($baseUnit));
        $this->assertTrue($found->purchaseUnit()->equals($caseUnit));
        $this->assertTrue($found->saleUnit()->equals($dozenUnit));
        $this->assertCount(2, $found->conversionRules());

        // Find by ID
        $found2 = $repo->findOrFail($configId);
        $this->assertEquals($configId, $found2->id);

        // Update configuration (add new rule, change sale unit)
        $packUnit = new UnitOfMeasure('Pack', 'pk', UomCategory::Discrete);
        $found->addConversionRule($packUnit, 6.0, 'Pack of 6');
        $found->setSaleUnit($packUnit);

        $repo->save($found);

        // Reload
        $reloaded = $repo->findOrFail($configId);
        $this->assertCount(3, $reloaded->conversionRules());
        $this->assertTrue($reloaded->saleUnit()->equals($packUnit));
    }
}
