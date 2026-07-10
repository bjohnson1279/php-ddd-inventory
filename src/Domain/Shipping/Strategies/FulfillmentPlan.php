<?php

namespace InventoryApp\Domain\Shipping\Strategies;

class FulfillmentPlan
{
    /**
     * @param FulfillmentAllocation[] $allocations
     */
    public function __construct(
        public readonly array $allocations,
        public readonly int $estimatedShippingCostCents,
        public readonly float $totalDistanceKm,
        public readonly int $splitCount,
        public float $score = 0.0
    ) {}
}
