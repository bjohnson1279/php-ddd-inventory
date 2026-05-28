<?php

namespace InventoryApp\Domain\Inventory\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use DateTimeImmutable;

final class ReturnProcessed implements DomainEvent
{
    public function __construct(
        public readonly SKU            $sku,
        public readonly LocationId     $locationId,
        public readonly int            $quantity,
        public readonly Condition      $condition,
        public readonly ?string        $orderId,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function getSku(): SKU               { return $this->sku; }
    public function getLocationId(): LocationId { return $this->locationId; }

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
