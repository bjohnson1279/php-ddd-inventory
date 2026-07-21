<?php

namespace InventoryApp\Domain\Shipping\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class ShipmentStatusUpdatedEvent implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $shipmentId,
        public readonly string $trackingNumber,
        public readonly string $status,
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
