<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;

class InventoryCountItem
{
    private SKU $sku;
    private LocationId $locationId;
    private Quantity $countedQuantity;

    public function __construct(SKU $sku, LocationId $locationId, Quantity $countedQuantity)
    {
        $this->sku = $sku;
        $this->locationId = $locationId;
        $this->countedQuantity = $countedQuantity;
    }

    public function getSku(): SKU
    {
        return $this->sku;
    }

    public function getLocationId(): LocationId
    {
        return $this->locationId;
    }

    public function getCountedQuantity(): Quantity
    {
        return $this->countedQuantity;
    }
}
