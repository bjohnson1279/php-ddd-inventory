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

    public function testFactorToBaseReturnsOneForBaseUnit()
    {
        $base = new UnitOfMeasure('Piece', 'pc', UomCategory::Discrete);
        $config = new ProductUomConfiguration('cfg-1', 'variant-1', $base);

        $this->assertEquals(1.0, $config->factorToBase($base));
    }

    public function testFactorToBaseCalculatesWeightCorrectly()
    {
        $base = new UnitOfMeasure('Gram', 'g', UomCategory::Weight);
        $kg = new UnitOfMeasure('Kilogram', 'kg', UomCategory::Weight);

        $config = new ProductUomConfiguration('cfg-1', 'variant-1', $base);

        $this->assertEquals(1000.0, $config->factorToBase($kg));
    }

    public function testFactorToBaseCalculatesVolumeCorrectly()
    {
        $base = new UnitOfMeasure('Milliliter', 'ml', UomCategory::Volume);
        $liter = new UnitOfMeasure('Liter', 'l', UomCategory::Volume);

        $config = new ProductUomConfiguration('cfg-1', 'variant-1', $base);

        $this->assertEquals(1000.0, $config->factorToBase($liter));
    }

    public function testFactorToBaseUsesConversionRuleForDiscreteUnits()
    {
        $base = new UnitOfMeasure('Piece', 'pc', UomCategory::Discrete);
        $dozen = new UnitOfMeasure('Dozen', 'dz', UomCategory::Discrete);

        $config = new ProductUomConfiguration('cfg-1', 'variant-1', $base);
        $config->addConversionRule($dozen, 12.0);

        $this->assertEquals(12.0, $config->factorToBase($dozen));
    }
}
