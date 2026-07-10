<?php

namespace InventoryApp\Domain\Shipping\Strategies;

interface RoutingStrategyInterface
{
    public function score(FulfillmentPlan $plan): float;
}
