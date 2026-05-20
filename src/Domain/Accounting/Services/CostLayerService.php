<?php

namespace InventoryApp\Domain\Accounting\Services;

use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use DomainException;

class CostLayerService
{
    public function __construct(
        private readonly CostLayerRepositoryInterface $layers,
    ) {}

    public function consumeFifoLayers(string $variantId, int $quantity): CostBreakdown
    {
        $activeLayers = $this->layers->getActiveLayers($variantId, 'received_at ASC');
        $breakdown = $this->consumeLayers($activeLayers, $quantity);

        foreach ($activeLayers as $layer) {
            $this->layers->save($layer);
        }

        return $breakdown;
    }

    public function calculateWeightedAverageCost(string $variantId, int $quantity): CostBreakdown
    {
        $activeLayers = $this->layers->getActiveLayers($variantId);

        $totalUnits = array_sum(
            array_map(fn(InventoryCostLayer $l) => $l->remainingQuantity(), $activeLayers)
        );
        $totalValue = array_sum(
            array_map(fn(InventoryCostLayer $l) => $l->remainingCostCents(), $activeLayers)
        );

        if ($totalUnits === 0) {
            throw new DomainException("Insufficient inventory cost layers for variant {$variantId}");
        }

        $avgCostCents = $totalValue / $totalUnits;
        return new CostBreakdown($quantity, (int) round($quantity * $avgCostCents));
    }

    /** @param InventoryCostLayer[] $layers */
    private function consumeLayers(array $layers, int $quantity): CostBreakdown
    {
        $remaining = $quantity;
        $totalCost = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) break;

            $consumed = $layer->consume($remaining);
            $totalCost += $consumed * $layer->unitCostCents;
            $remaining -= $consumed;
        }

        if ($remaining > 0) {
            throw new DomainException("Insufficient cost layers to cover quantity {$quantity}");
        }

        return new CostBreakdown($quantity, $totalCost);
    }
}
