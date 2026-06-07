<?php

namespace Tests\Unit\Domain\Uom\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Enums\UomCategory;
use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;

class ProductUomConfigurationTest extends TestCase
{
    public function testFactorToBaseThrowsExceptionWhenNoConversionRuleFound()
    {
        $base = new UnitOfMeasure('Piece', 'pc', UomCategory::Discrete);
        $unmappedUnit = new UnitOfMeasure('Box', 'bx', UomCategory::Discrete);

        $config = new ProductUomConfiguration('cfg-1', 'variant-1', $base);

        $this->expectException(\DomainException::class);

        $config->factorToBase($unmappedUnit);
    }
}
