<?php

namespace InventoryApp\Domain\Shipping\Strategies;

class MinimizeSplitsStrategy implements RoutingStrategyInterface
{
    public function score(FulfillmentPlan $plan): float
    {
        $splitPenalty = $plan->splitCount * 1000000;
        $costFactor = $plan->estimatedShippingCostCents;
        $distanceFactor = $plan->totalDistanceKm * 0.1;
        return $splitPenalty + $costFactor + $distanceFactor;
    }
}
