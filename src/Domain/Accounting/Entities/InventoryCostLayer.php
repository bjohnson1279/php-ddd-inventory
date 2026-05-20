<?php

namespace InventoryApp\Domain\Accounting\Entities;

class InventoryCostLayer
{
    private int $remainingQuantity;

    public function __construct(
        public readonly string $id,
        public readonly string $variantId,
        public readonly string $tenantId,
        public readonly int $originalQuantity,
        public readonly int $unitCostCents,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly ?string $purchaseOrderId = null
    ) {
        $this->remainingQuantity = $originalQuantity;
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
}
