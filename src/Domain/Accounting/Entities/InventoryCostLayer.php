<?php

namespace InventoryApp\Domain\Accounting\Entities;

use InvalidArgumentException;
use InventoryApp\Domain\Shared\ValueObjects\Money;

class InventoryCostLayer
{
    private int $remainingQuantity;
    public readonly Money $unitCost;
    public readonly int $unitCostCents;
    public ?string $serialNumber = null;
    public ?string $lotNumber = null;
    public ?\DateTimeImmutable $expirationDate = null;

    public function __construct(
        public readonly string $id,
        public readonly string $variantId,
        public readonly string $tenantId,
        public readonly int $originalQuantity,
        Money|int $unitCostCents,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly ?string $purchaseOrderId = null
    ) {
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

        $this->remainingQuantity = $originalQuantity;
    }

    public function getUnitCost(): Money
    {
        return $this->unitCost;
    }

    public function getRemainingCost(): Money
    {
        return $this->unitCost->multiply($this->remainingQuantity);
    }

    public function consume(int $needed): int
    {
        $consumed = min($needed, $this->remainingQuantity);
        $this->remainingQuantity -= $consumed;
        return $consumed;
    }

    public function remainingQuantity(): int
    {
        return $this->remainingQuantity;
    }

    public function remainingCostCents(): int
    {
        return $this->remainingQuantity * $this->unitCostCents;
    }

    public function isExhausted(): bool
    {
        return $this->remainingQuantity === 0;
    }

    public function setRemainingQuantity(int $quantity): void
    {
        $this->remainingQuantity = $quantity;
    }
}
