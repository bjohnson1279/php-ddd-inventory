<?php

namespace InventoryApp\Domain\Accounting\Strategies;

use InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown;
use DomainException;

class LifoCostingStrategy implements CostingStrategyInterface
{
    public function calculateCost(array $layers, int $quantity, string $variantId): CostBreakdown
    {
        $sorted = $layers;
        usort($sorted, fn($a, $b) => $b->receivedAt <=> $a->receivedAt);

        $remaining = $quantity;
        $totalCost = 0;

        foreach ($sorted as $layer) {
            if ($remaining <= 0) break;
            $consumed = min($remaining, $layer->remainingQuantity());
            $totalCost += $consumed * $layer->unitCostCents;
            $remaining -= $consumed;
        }

        if ($remaining > 0) {
            throw new DomainException("Insufficient cost layers to cover quantity {$quantity}");
        }

        return new CostBreakdown($quantity, $totalCost);
    }

    public function consumeLayers(array $layers, int $quantity, string $variantId): array
    {
        $sorted = $layers;
        usort($sorted, fn($a, $b) => $b->receivedAt <=> $a->receivedAt);

        $remaining = $quantity;
        $totalCost = 0;
        $affectedLayers = [];

        foreach ($sorted as $layer) {
            if ($remaining <= 0) break;
            $consumed = $layer->consume($remaining);
            $totalCost += $consumed * $layer->unitCostCents;
            $remaining -= $consumed;
            $affectedLayers[] = $layer;
        }

        if ($remaining > 0) {
            throw new DomainException("Insufficient cost layers to cover quantity {$quantity}");
        }

        return [new CostBreakdown($quantity, $totalCost), $affectedLayers];
    }
}
