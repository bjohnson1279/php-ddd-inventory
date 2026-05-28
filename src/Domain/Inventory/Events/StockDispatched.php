<?php

namespace InventoryApp\Domain\Inventory\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;

final class StockDispatched implements DomainEvent
{
    public function __construct(
        public readonly SKU            $sku,
        public readonly LocationId     $locationId,
        public readonly int            $quantity,
        public readonly ?string        $reference,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function getSku(): SKU               { return $this->sku; }
    public function getLocationId(): LocationId { return $this->locationId; }

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
