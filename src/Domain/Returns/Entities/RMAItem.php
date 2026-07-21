<?php

namespace InventoryApp\Domain\Returns\Entities;

use InventoryApp\Domain\Returns\Enums\RMAItemStatus;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InvalidArgumentException;

class RMAItem
{
    private string $id;
    private string $variantId;
    private int $quantity;
    private int $unitCostCents;
    private int $receivedQuantity;
    private RMAItemStatus $status;
    private ?RMADisposition $disposition;

    public function __construct(
        string $id,
        string $variantId,
        int $quantity,
        int $unitCostCents,
        int $receivedQuantity = 0,
        RMAItemStatus $status = RMAItemStatus::Pending,
        ?RMADisposition $disposition = null
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity must be greater than zero.");
        }
        if ($unitCostCents < 0) {
            throw new InvalidArgumentException("Unit cost cannot be negative.");
        }
        if ($receivedQuantity < 0) {
            throw new InvalidArgumentException("Received quantity cannot be negative.");
        }

        $this->id = $id;
        $this->variantId = $variantId;
        $this->quantity = $quantity;
        $this->unitCostCents = $unitCostCents;
        $this->receivedQuantity = $receivedQuantity;
        $this->status = $status;
        $this->disposition = $disposition;
    }

    public function getId(): string { return $this->id; }
    public function getVariantId(): string { return $this->variantId; }
    public function getQuantity(): int { return $this->quantity; }
    public function getUnitCostCents(): int { return $this->unitCostCents; }
    public function getReceivedQuantity(): int { return $this->receivedQuantity; }
    public function getStatus(): RMAItemStatus { return $this->status; }
    public function getDisposition(): ?RMADisposition { return $this->disposition; }

    public function receive(int $amount, RMADisposition $disposition): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Receive quantity must be greater than zero.");
        }
        if ($this->receivedQuantity + $amount > $this->quantity) {
            throw new InvalidArgumentException("Cannot receive {$amount} units. Total received would exceed expected quantity of {$this->quantity}.");
        }

        $this->receivedQuantity += $amount;
        $this->disposition = $disposition;

        if ($this->receivedQuantity === $this->quantity) {
            $this->status = RMAItemStatus::Received;
        } else {
            $this->status = RMAItemStatus::Pending;
        }
    }

    public function reject(): void
    {
        $this->status = RMAItemStatus::Rejected;
    }

    public function isFullyProcessed(): bool
    {
        return $this->status === RMAItemStatus::Received || $this->status === RMAItemStatus::Rejected;
    }
}
