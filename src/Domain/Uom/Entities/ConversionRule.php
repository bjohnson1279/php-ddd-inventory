<?php

namespace InventoryApp\Domain\Uom\Entities;

use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;

final class ConversionRule
{
    public function __construct(
        public readonly string $id,
        public readonly UnitOfMeasure $unit,
        public readonly float $factorToBase,
        public readonly ?string $label = null,
    ) {
        if ($factorToBase <= 0) throw new \InvalidArgumentException('Conversion factor must be positive.');
        if ($factorToBase === 1.0) throw new \InvalidArgumentException('Conversion factor of 1.0 duplicates base unit.');
    }

    public function factorFromBase(): float { return 1.0 / $this->factorToBase; }
}
