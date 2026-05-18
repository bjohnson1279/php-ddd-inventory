<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;

class InventoryCountItem
{
    private SKU $sku;
    private Quantity $countedQuantity;

    public function __construct(SKU $sku, Quantity $countedQuantity)
    {
        $this->sku = $sku;
        $this->countedQuantity = $countedQuantity;
    }

    public function getSku(): SKU
    {
        return $this->sku;
    }

    public function getCountedQuantity(): Quantity
    {
        return $this->countedQuantity;
    }
}
