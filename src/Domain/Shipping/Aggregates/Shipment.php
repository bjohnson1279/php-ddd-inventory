<?php

namespace InventoryApp\Domain\Shipping\Aggregates;

use InventoryApp\Domain\Shared\ValueObjects\Money;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

class Shipment
{
    public readonly Money $shippingRate;
    public readonly int $shippingRateCents;

    public function __construct(
        public readonly string $id,
        public readonly string $sku,
        public readonly int $quantity,
        public readonly string $destinationAddress,
        public readonly string $carrier,
        public ?string $trackingNumber,
        public ?string $labelUrl,
        Money|int $shippingRateCents,
        private ShipmentStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null
    ) {
        if ($shippingRateCents instanceof Money) {
            $this->shippingRate = $shippingRateCents;
            $this->shippingRateCents = $shippingRateCents->getAmountInCents();
        } else {
            if ($shippingRateCents < 0) {
                throw new InvalidArgumentException("Shipping rate cannot be negative.");
            }
            $this->shippingRate = Money::fromCents($shippingRateCents);
            $this->shippingRateCents = $shippingRateCents;
        }

        if ($this->shippingRate->isNegative()) {
            throw new InvalidArgumentException("Shipping rate cannot be negative.");
        }

        if ($this->updatedAt === null) {
            $this->updatedAt = $createdAt;
        }
    }

    public function getShippingRate(): Money
    {
        return $this->shippingRate;
    }

    public function getShippingRateCents(): int
    {
        return $this->shippingRateCents;
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
