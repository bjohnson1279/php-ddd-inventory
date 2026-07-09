<?php

namespace InventoryApp\Domain\Shipping\Strategies;

class MinimizeCostStrategy implements RoutingStrategyInterface
{
    public function score(FulfillmentPlan $plan): float
    {
        $splitPenalty = $plan->splitCount * 500;
        $costFactor = $plan->estimatedShippingCostCents;
        $distanceFactor = $plan->totalDistanceKm * 0.1;
        return $splitPenalty + $costFactor + $distanceFactor;
    }
}
