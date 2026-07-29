<?php

namespace InventoryApp\Domain\Inventory\Entities;

use DateTimeImmutable;

class LotBatch
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $lotNumber,
        public readonly string $variantId,
        public string $status = 'ACTIVE',
        public readonly ?DateTimeImmutable $manufacturedDate = null,
        public readonly ?DateTimeImmutable $expirationDate = null,
        public readonly ?string $supplierId = null,
        public ?DateTimeImmutable $quarantinedAt = null,
        public ?string $quarantineReason = null,
        public ?DateTimeImmutable $recalledAt = null,
        public readonly DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {}

    public function isAvailable(): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }
        if ($this->expirationDate !== null && $this->expirationDate->getTimestamp() <= time()) {
            return false;
        }
        return true;
    }

    public function quarantine(string $reason): void
    {
        $this->status = 'QUARANTINED';
        $this->quarantinedAt = new DateTimeImmutable();
        $this->quarantineReason = $reason;
    }

    public function recall(string $reason): void
    {
        $this->status = 'RECALLED';
        $this->recalledAt = new DateTimeImmutable();
        $this->quarantineReason = $reason;
    }

    public function release(): void
    {
        $this->status = 'ACTIVE';
        $this->quarantinedAt = null;
        $this->quarantineReason = null;
        $this->recalledAt = null;
    }
}
