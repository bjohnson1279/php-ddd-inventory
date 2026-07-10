<?php

namespace InventoryApp\Domain\Shipping\Strategies;

class FulfillmentAllocation
{
    public function __construct(
        public readonly string $locationId,
        public readonly int $quantity
    ) {}
}
