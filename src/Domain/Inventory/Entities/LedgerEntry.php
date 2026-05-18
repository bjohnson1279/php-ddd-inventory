<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\Enums\ReasonCode;

final class LedgerEntry
{
    public readonly string $id;
    public readonly string $variantId;
    public readonly int $quantity;
    public readonly ReasonCode $reason;
    public readonly string $actorId;
    public readonly ?string $referenceId;
    public readonly \DateTimeImmutable $occurredAt;
    public readonly array $metadata;

    public function __construct(
        string $id,
        string $variantId,
        int $quantity,
        ReasonCode $reason,
        string $actorId,
        ?string $referenceId,
        \DateTimeImmutable $occurredAt,
        array $metadata = [],
    ) {
        if ($quantity === 0) {
            throw new \InvalidArgumentException('A ledger entry quantity cannot be zero.');
        }

        $this->id = $id;
        $this->variantId = $variantId;
        $this->quantity = $quantity;
        $this->reason = $reason;
        $this->actorId = $actorId;
        $this->referenceId = $referenceId;
        $this->occurredAt = $occurredAt;
        $this->metadata = $metadata;
    }

    public function isDeduction(): bool
    {
        return $this->quantity < 0;
    }
}
