<?php

namespace InventoryApp\Domain\Shipping\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class ShipmentCreatedEvent implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $shipmentId,
        public readonly string $sku,
        public readonly int $quantity,
        public readonly string $carrier,
        public readonly string $trackingNumber,
        public readonly int $rateCents,
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
