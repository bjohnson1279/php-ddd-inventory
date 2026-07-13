<?php

namespace InventoryApp\Domain\Accounting\Strategies;

use InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown;

interface CostingStrategyInterface
{
    /**
     * @param \InventoryApp\Domain\Accounting\Entities\InventoryCostLayer[] $layers
     */
    public function calculateCost(array $layers, int $quantity, string $variantId): CostBreakdown;

    /**
     * @param \InventoryApp\Domain\Accounting\Entities\InventoryCostLayer[] $layers
     * @return array{0: CostBreakdown, 1: \InventoryApp\Domain\Accounting\Entities\InventoryCostLayer[]}
     */
    public function consumeLayers(array $layers, int $quantity, string $variantId): array;
}
