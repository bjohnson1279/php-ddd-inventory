<?php

namespace InventoryApp\Domain\Procurement\Entities;

use InvalidArgumentException;
use InventoryApp\Domain\Shared\ValueObjects\Money;

class PurchaseOrderItem
{
    private int $receivedQuantity;
    public readonly Money $unitCost;
    public readonly int $unitCostCents;

    public function __construct(
        public readonly string $id,
        public readonly string $variantId,
        public readonly int $quantity,
        Money|int $unitCostCents,
        int $receivedQuantity = 0
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity must be greater than zero.");
        }

        if ($unitCostCents instanceof Money) {
            $this->unitCost = $unitCostCents;
            $this->unitCostCents = $unitCostCents->getAmountInCents();
        } else {
            if ($unitCostCents < 0) {
                throw new InvalidArgumentException("Unit cost cannot be negative.");
            }
            $this->unitCost = Money::fromCents($unitCostCents);
            $this->unitCostCents = $unitCostCents;
        }

        if ($this->unitCost->isNegative()) {
            throw new InvalidArgumentException("Unit cost cannot be negative.");
        }

        if ($receivedQuantity < 0) {
            throw new InvalidArgumentException("Received quantity cannot be negative.");
        }
        if ($receivedQuantity > $quantity) {
            throw new InvalidArgumentException(
                "Received quantity cannot exceed ordered quantity."
            );
        }
        $this->receivedQuantity = $receivedQuantity;
    }

    public function getUnitCost(): Money
    {
        return $this->unitCost;
    }

    public function getTotalCost(): Money
    {
        return $this->unitCost->multiply($this->quantity);
    }

    public function getReceivedQuantity(): int
    {
        return $this->receivedQuantity;
    }

    public function receive(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Receive amount must be greater than zero.");
        }
        if ($this->receivedQuantity + $amount > $this->quantity) {
            throw new InvalidArgumentException(
                "Cannot receive {$amount} items. Total received would exceed ordered quantity of {$this->quantity}."
            );
        }
        $this->receivedQuantity += $amount;
    }

    public function isFullyReceived(): bool
    {
        return $this->receivedQuantity === $this->quantity;
    }
}
