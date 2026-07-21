<?php

namespace InventoryApp\Domain\Procurement\Entities;

use InvalidArgumentException;

class PurchaseOrderItem
{
    private int $receivedQuantity;

    public function __construct(
        public readonly string $id,
        public readonly string $variantId,
        public readonly int $quantity,
        public readonly int $unitCostCents,
        int $receivedQuantity = 0
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity must be greater than zero.");
        }
        if ($unitCostCents < 0) {
            throw new InvalidArgumentException("Unit cost cannot be negative.");
        }
        $this->receivedQuantity = $receivedQuantity;
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
