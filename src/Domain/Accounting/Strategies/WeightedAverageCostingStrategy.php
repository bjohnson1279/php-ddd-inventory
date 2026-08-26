<?php

namespace InventoryApp\Domain\Accounting\Strategies;

use InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown;
use DomainException;

class WeightedAverageCostingStrategy implements CostingStrategyInterface
{
    public function calculateCost(array $layers, int $quantity, string $variantId): CostBreakdown
    {
        // Bolt ⚡ Optimization: Replace array_sum(array_map) with single pass loop
        // CPU/Mem metric: Eliminates 2 intermediate array allocations and O(N) traversals.
        $totalUnits = 0;
        $totalValue = 0;
        foreach ($layers as $l) {
            $totalUnits += $l->remainingQuantity();
            $totalValue += $l->remainingCostCents();
        }

        if ($totalUnits === 0 || $totalUnits < $quantity) {
            throw new DomainException("Insufficient cost layers to cover quantity {$quantity}");
        }

        $avgCostCents = $totalValue / $totalUnits;
        return new CostBreakdown($quantity, (int) round($quantity * $avgCostCents));
    }

    public function consumeLayers(array $layers, int $quantity, string $variantId): array
    {
        $breakdown = $this->calculateCost($layers, $quantity, $variantId);

        $sorted = $layers;
        usort($sorted, fn($a, $b) => $a->receivedAt <=> $b->receivedAt);

        $remaining = $quantity;
        $affectedLayers = [];

        foreach ($sorted as $layer) {
            if ($remaining <= 0) break;
            $consumed = $layer->consume($remaining);
            $remaining -= $consumed;
            $affectedLayers[] = $layer;
        }

        return [$breakdown, $affectedLayers];
    }
}
