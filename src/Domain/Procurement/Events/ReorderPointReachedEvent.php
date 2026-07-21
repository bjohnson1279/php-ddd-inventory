<?php

namespace InventoryApp\Domain\Procurement\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class ReorderPointReachedEvent implements DomainEvent
{
    public function __construct(
        public readonly string $sku,
        public readonly string $locationId,
        public readonly int $currentQuantity,
        public readonly int $reorderPoint,
        public readonly int $reorderQuantity,
        private readonly DateTimeImmutable $occurredOn
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
