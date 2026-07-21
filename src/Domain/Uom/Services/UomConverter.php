<?php

namespace InventoryApp\Domain\Uom\Services;

use InventoryApp\Domain\Uom\ValueObjects\Quantity;
use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;

class UomConverter
{
    public function convert(Quantity $from, UnitOfMeasure $toUnit, ProductUomConfiguration $config): Quantity
    {
        if ($from->unit->equals($toUnit)) return $from;
        if (!$from->unit->isCompatibleWith($toUnit)) throw new \DomainException('Incompatible unit categories');

        $inBase = $from->amount * $config->factorToBase($from->unit);
        $targetFactor = $config->factorToBase($toUnit);
        $converted = $inBase / $targetFactor;
        return new Quantity($converted, $toUnit);
    }

    public function toBaseUnit(Quantity $quantity, ProductUomConfiguration $config): Quantity
    {
        return $this->convert($quantity, $config->baseUnit(), $config);
    }

    public function convertCost(int $costCentsPerUnit, UnitOfMeasure $perUnit, UnitOfMeasure $targetUnit, ProductUomConfiguration $config): int
    {
        if ($perUnit->equals($targetUnit)) return $costCentsPerUnit;
        $factorPerToBase = $config->factorToBase($perUnit);
        $factorTargetToBase = $config->factorToBase($targetUnit);
        $ratio = $factorPerToBase / $factorTargetToBase;
        return (int) round($costCentsPerUnit / $ratio);
    }
}
