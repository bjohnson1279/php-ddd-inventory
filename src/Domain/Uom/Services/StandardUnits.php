<?php

namespace InventoryApp\Domain\Uom\Services;

use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Enums\UomCategory;

final class StandardUnits
{
    public static function each(): UnitOfMeasure { return new UnitOfMeasure('Each', 'ea', UomCategory::Discrete); }
    public static function dozen(): UnitOfMeasure { return new UnitOfMeasure('Dozen', 'dz', UomCategory::Discrete); }
    public static function case(int $unitsPerCase): UnitOfMeasure { return new UnitOfMeasure('Case', 'cs', UomCategory::Discrete); }

    public static function gram(): UnitOfMeasure { return new UnitOfMeasure('Gram', 'g', UomCategory::Weight); }
    public static function kilogram(): UnitOfMeasure { return new UnitOfMeasure('Kilogram', 'kg', UomCategory::Weight); }
    public static function ounce(): UnitOfMeasure { return new UnitOfMeasure('Ounce', 'oz', UomCategory::Weight); }
    public static function pound(): UnitOfMeasure { return new UnitOfMeasure('Pound', 'lb', UomCategory::Weight); }

    public static function milliliter(): UnitOfMeasure { return new UnitOfMeasure('Milliliter', 'ml', UomCategory::Volume); }
    public static function liter(): UnitOfMeasure { return new UnitOfMeasure('Liter', 'l', UomCategory::Volume); }
    public static function fluidOunce(): UnitOfMeasure { return new UnitOfMeasure('Fluid Ounce', 'fl oz', UomCategory::Volume); }
    public static function gallon(): UnitOfMeasure { return new UnitOfMeasure('Gallon', 'gal', UomCategory::Volume); }

    public static function weightFactorToGrams(UnitOfMeasure $unit): float
    {
        return match($unit->name) {
            'Gram' => 1.0,
            'Kilogram' => 1000.0,
            'Ounce' => 28.3495,
            'Pound' => 453.592,
            default => throw new \DomainException("Unknown weight unit: {$unit->name}"),
        };
    }

    public static function volumeFactorToMilliliters(UnitOfMeasure $unit): float
    {
        return match($unit->name) {
            'Milliliter' => 1.0,
            'Liter' => 1000.0,
            'Fluid Ounce' => 29.5735,
            'Cup' => 236.588,
            'Gallon' => 3785.41,
            default => throw new \DomainException("Unknown volume unit: {$unit->name}"),
        };
    }
}
