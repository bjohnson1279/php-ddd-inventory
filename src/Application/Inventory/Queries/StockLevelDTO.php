<?php

namespace InventoryApp\Application\Inventory\Queries;

class StockLevelDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly string $locationId,
        public readonly int $stockQuantity,
        public readonly int $allocatedQuantity = 0,
        public readonly int $inTransitQuantity = 0,
        public readonly int $availableQuantity = 0
    ) {}
}
