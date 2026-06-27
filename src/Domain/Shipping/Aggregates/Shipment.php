<?php

namespace InventoryApp\Domain\Shipping\Aggregates;

use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use DateTimeImmutable;
use DomainException;

class Shipment
{
    public function __construct(
        public readonly string $id,
        public readonly string $sku,
        public readonly int $quantity,
        public readonly string $destinationAddress,
        public readonly string $carrier,
        public ?string $trackingNumber,
        public ?string $labelUrl,
        public readonly int $shippingRateCents,
        private ShipmentStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->updatedAt === null) {
            $this->updatedAt = $createdAt;
        }
    }

    public function getStatus(): ShipmentStatus
    {
        return $this->status;
    }

    public function updateStatus(ShipmentStatus $newStatus): void
    {
        if ($this->status === ShipmentStatus::Delivered || $this->status === ShipmentStatus::Failed) {
            throw new DomainException("Cannot transition status from terminal state: " . $this->status->value);
        }

        $this->status = $newStatus;
        $this->updatedAt = new DateTimeImmutable();
    }
}
