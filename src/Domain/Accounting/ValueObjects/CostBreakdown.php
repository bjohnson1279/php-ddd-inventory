<?php

namespace InventoryApp\Domain\Accounting\ValueObjects;

final class CostBreakdown
{
    public function __construct(
        public readonly int $units,
        public readonly int $totalCostCents,
    ) {}

    public function unitCostCents(): int
    {
        return $this->units > 0
            ? (int) round($this->totalCostCents / $this->units)
            : 0;
    }
}
