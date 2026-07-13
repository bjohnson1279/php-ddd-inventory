<?php

namespace InventoryApp\Domain\Shipping\Strategies;

class MinimizeDistanceStrategy implements RoutingStrategyInterface
{
    public function score(FulfillmentPlan $plan): float
    {
        $splitPenalty = $plan->splitCount * 1000;
        $costFactor = $plan->estimatedShippingCostCents * 0.1;
        $distanceFactor = $plan->totalDistanceKm * 10;
        return $splitPenalty + $costFactor + $distanceFactor;
    }
}
