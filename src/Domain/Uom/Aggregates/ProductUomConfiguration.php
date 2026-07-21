<?php

namespace InventoryApp\Domain\Uom\Aggregates;

use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Entities\ConversionRule;
use InventoryApp\Domain\Uom\Enums\UomCategory;
use InventoryApp\Domain\Uom\Services\StandardUnits;

class ProductUomConfiguration
{
    private UnitOfMeasure $baseUnit;
    private ?UnitOfMeasure $purchaseUnit = null;
    private ?UnitOfMeasure $saleUnit = null;
    private array $conversionRules = []; // ConversionRule[]

    public function __construct(public readonly string $id, public readonly string $variantId, UnitOfMeasure $baseUnit)
    {
        $this->baseUnit = $baseUnit;
    }

    public function addConversionRule(UnitOfMeasure $unit, float $factorToBase, ?string $label = null): void
    {
        if (!$unit->isCompatibleWith($this->baseUnit)) throw new \DomainException('Incompatible units');
        if ($unit->equals($this->baseUnit)) throw new \InvalidArgumentException('Cannot add base unit rule');
        foreach ($this->conversionRules as $existing) {
            if ($existing->unit->equals($unit)) throw new \DomainException('Conversion rule exists');
        }
        $this->conversionRules[] = new ConversionRule(\Ramsey\Uuid\Uuid::uuid4()->toString(), $unit, $factorToBase, $label);
    }

    public function removeConversionRule(UnitOfMeasure $unit): void
    {
        $this->conversionRules = array_values(array_filter(
            $this->conversionRules,
            fn(ConversionRule $r) => !$r->unit->equals($unit),
        ));
    }

    public function setPurchaseUnit(UnitOfMeasure $unit): void { $this->assertUnitIsKnown($unit); $this->purchaseUnit = $unit; }
    public function setSaleUnit(UnitOfMeasure $unit): void { $this->assertUnitIsKnown($unit); $this->saleUnit = $unit; }

    public function baseUnit(): UnitOfMeasure { return $this->baseUnit; }
    public function purchaseUnit(): UnitOfMeasure { return $this->purchaseUnit ?? $this->baseUnit; }
    public function saleUnit(): UnitOfMeasure { return $this->saleUnit ?? $this->baseUnit; }

    public function factorToBase(UnitOfMeasure $unit): float
    {
        if ($unit->equals($this->baseUnit)) return 1.0;
        if ($unit->category === UomCategory::Weight) {
            $unitFactor = StandardUnits::weightFactorToGrams($unit);
            $baseFactor = StandardUnits::weightFactorToGrams($this->baseUnit);
            return $unitFactor / $baseFactor;
        }
        if ($unit->category === UomCategory::Volume) {
            $unitFactor = StandardUnits::volumeFactorToMilliliters($unit);
            $baseFactor = StandardUnits::volumeFactorToMilliliters($this->baseUnit);
            return $unitFactor / $baseFactor;
        }
        foreach ($this->conversionRules as $rule) {
            if ($rule->unit->equals($unit)) return $rule->factorToBase;
        }
        throw new \DomainException('No conversion rule found');
    }

    public function conversionRules(): array { return $this->conversionRules; }

    private function assertUnitIsKnown(UnitOfMeasure $unit): void
    {
        if ($unit->equals($this->baseUnit)) return;
        foreach ($this->conversionRules as $rule) if ($rule->unit->equals($unit)) return;
        throw new \DomainException('Unit has no conversion rule defined');
    }
}
