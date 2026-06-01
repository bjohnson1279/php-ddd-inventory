<?php

namespace InventoryApp\Application\Inventory\Queries;

class StockLevelDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly string $locationId,
        public readonly int $stockQuantity
    ) {}
}
