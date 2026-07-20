<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientAvailableStockException;

class LocationStock
{
    private LocationId $locationId;
    private Quantity $stockQuantity;
    private Quantity $openBoxQuantity;
    private Quantity $damagedQuantity;
    private Quantity $allocatedQuantity;
    private Quantity $inTransitQuantity;

    public function __construct(
        LocationId $locationId,
        Quantity $stockQuantity,
        ?Quantity $openBoxQuantity = null,
        ?Quantity $damagedQuantity = null,
        ?Quantity $allocatedQuantity = null,
        ?Quantity $inTransitQuantity = null
    ) {
        $this->locationId = $locationId;
        $this->stockQuantity = $stockQuantity;
        $this->openBoxQuantity = $openBoxQuantity ?? new Quantity(0);
        $this->damagedQuantity = $damagedQuantity ?? new Quantity(0);
        $this->allocatedQuantity = $allocatedQuantity ?? new Quantity(0);
        $this->inTransitQuantity = $inTransitQuantity ?? new Quantity(0);
    }

    public function getLocationId(): LocationId { return $this->locationId; }
    public function getStockQuantity(): Quantity { return $this->stockQuantity; }
    public function getOpenBoxQuantity(): Quantity { return $this->openBoxQuantity; }
    public function getDamagedQuantity(): Quantity { return $this->damagedQuantity; }
    public function getAllocatedQuantity(): Quantity { return $this->allocatedQuantity; }
    public function getInTransitQuantity(): Quantity { return $this->inTransitQuantity; }

    public function getAvailableQuantity(): Quantity
    {
        $val = $this->stockQuantity->getValue() - $this->allocatedQuantity->getValue() + $this->inTransitQuantity->getValue();
        return new Quantity($val < 0 ? 0 : $val);
    }

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

    public function allocate(Quantity $amount, string $skuValue): void
    {
        if ($this->getAvailableQuantity()->getValue() < $amount->getValue()) {
            throw new InsufficientAvailableStockException($skuValue, $amount->getValue(), $this->getAvailableQuantity()->getValue());
        }
        $this->allocatedQuantity = $this->allocatedQuantity->add($amount);
    }

    public function releaseAllocation(Quantity $amount): void
    {
        if ($this->allocatedQuantity->getValue() < $amount->getValue()) {
            throw new \Exception("Cannot release allocation of {$amount->getValue()} because only {$this->allocatedQuantity->getValue()} is allocated.");
        }
        $this->allocatedQuantity = $this->allocatedQuantity->subtract($amount);
    }

    public function fulfillAllocation(Quantity $amount): void
    {
        if ($this->allocatedQuantity->getValue() < $amount->getValue()) {
            throw new \Exception("Cannot fulfill allocation of {$amount->getValue()} because only {$this->allocatedQuantity->getValue()} is allocated.");
        }
        if ($this->stockQuantity->getValue() < $amount->getValue()) {
            throw new \Exception("Cannot fulfill allocation of {$amount->getValue()} because only {$this->stockQuantity->getValue()} is in stock.");
        }
        $this->allocatedQuantity = $this->allocatedQuantity->subtract($amount);
        $this->stockQuantity = $this->stockQuantity->subtract($amount);
    }

    public function createInTransit(Quantity $amount): void
    {
        $this->inTransitQuantity = $this->inTransitQuantity->add($amount);
    }

    public function receiveInTransit(Quantity $amount): void
    {
        if ($this->inTransitQuantity->getValue() < $amount->getValue()) {
            throw new \Exception("Cannot receive in transit of {$amount->getValue()} because only {$this->inTransitQuantity->getValue()} is in transit.");
        }
        $this->inTransitQuantity = $this->inTransitQuantity->subtract($amount);
        $this->stockQuantity = $this->stockQuantity->add($amount);
    }

    public function cancelInTransit(Quantity $amount): void
    {
        if ($this->inTransitQuantity->getValue() < $amount->getValue()) {
            throw new \Exception("Cannot cancel in transit of {$amount->getValue()} because only {$this->inTransitQuantity->getValue()} is in transit.");
        }
        $this->inTransitQuantity = $this->inTransitQuantity->subtract($amount);
    }
}
