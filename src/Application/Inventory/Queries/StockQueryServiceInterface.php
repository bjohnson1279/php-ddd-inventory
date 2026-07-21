<?php

namespace InventoryApp\Application\Inventory\Queries;

interface StockQueryServiceInterface
{
    /**
     * Get the current stock level for a SKU. If locationId is provided,
     * returns the stock at that specific location. Otherwise, returns
     * the total stock across all locations.
     */
    public function getStockLevel(string $sku, ?string $locationId = null): StockLevelDTO;
}
