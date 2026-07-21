<?php

namespace InventoryApp\Domain\Procurement\Aggregates;

use InventoryApp\Domain\Shared\Entities\AggregateRoot;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InvalidArgumentException;

class ReorderPolicy extends AggregateRoot
{
    public function __construct(
        public readonly string $id,
        public readonly SKU $sku,
        public readonly string $locationId,
        public int $reorderPoint,
        public readonly int $reorderQuantity,
        public readonly int $safetyStock,
        public readonly bool $dynamicRopEnabled = false
    ) {
        if ($reorderPoint < 0) {
            throw new InvalidArgumentException("Reorder point cannot be negative.");
        }
        if ($reorderQuantity <= 0) {
            throw new InvalidArgumentException("Reorder quantity must be greater than zero.");
        }
        if ($safetyStock < 0) {
            throw new InvalidArgumentException("Safety stock cannot be negative.");
        }
    }

    public function updateReorderPoint(int $newRop): void
    {
        if ($newRop < 0) {
            throw new InvalidArgumentException("Reorder point cannot be negative.");
        }
        $this->reorderPoint = $newRop;
    }

    public function shouldReorder(int $currentQuantity): bool
    {
        return $currentQuantity <= $this->reorderPoint;
    }
}
