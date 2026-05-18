<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;

class LocationStock
{
    private LocationId $locationId;
    private Quantity $stockQuantity;
    private Quantity $openBoxQuantity;
    private Quantity $damagedQuantity;

    public function __construct(
        LocationId $locationId,
        Quantity $stockQuantity,
        ?Quantity $openBoxQuantity = null,
        ?Quantity $damagedQuantity = null
    ) {
        $this->locationId = $locationId;
        $this->stockQuantity = $stockQuantity;
        $this->openBoxQuantity = $openBoxQuantity ?? new Quantity(0);
        $this->damagedQuantity = $damagedQuantity ?? new Quantity(0);
    }

    public function getLocationId(): LocationId { return $this->locationId; }
    public function getStockQuantity(): Quantity { return $this->stockQuantity; }
    public function getOpenBoxQuantity(): Quantity { return $this->openBoxQuantity; }
    public function getDamagedQuantity(): Quantity { return $this->damagedQuantity; }

    public function addStock(Quantity $quantity, Condition $condition): void
    {
        if ($condition->getValue() === Condition::NEW) {
            $this->stockQuantity = $this->stockQuantity->add($quantity);
        } elseif ($condition->getValue() === Condition::OPEN_BOX) {
            $this->openBoxQuantity = $this->openBoxQuantity->add($quantity);
        } elseif ($condition->getValue() === Condition::DAMAGED) {
            $this->damagedQuantity = $this->damagedQuantity->add($quantity);
        }
    }

    public function subtractStock(string $skuValue, Quantity $quantity, Condition $condition): void
    {
        if ($condition->getValue() === Condition::NEW) {
            if (!$this->stockQuantity->isGreaterThanOrEqual($quantity)) {
                throw new InsufficientStockException($skuValue, $quantity->getValue(), $this->stockQuantity->getValue());
            }
            $this->stockQuantity = $this->stockQuantity->subtract($quantity);
        } elseif ($condition->getValue() === Condition::OPEN_BOX) {
            // Error handling omitted for brevity
            $this->openBoxQuantity = $this->openBoxQuantity->subtract($quantity);
        } elseif ($condition->getValue() === Condition::DAMAGED) {
            $this->damagedQuantity = $this->damagedQuantity->subtract($quantity);
        }
    }
}
