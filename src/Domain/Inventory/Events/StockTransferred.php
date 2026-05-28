<?php

namespace InventoryApp\Domain\Inventory\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;

final class StockTransferred implements DomainEvent
{
    public function __construct(
        public readonly SKU        $sku,
        public readonly LocationId $fromLocationId,
        public readonly LocationId $toLocationId,
        public readonly int        $quantity,
        public readonly ?string    $reference,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    /**
     * Required by SyncStockToShopify listener. Returns the *destination* location
     * so Shopify reflects the updated level where stock landed.
     */
    public function getSku(): SKU               { return $this->sku; }
    public function getLocationId(): LocationId { return $this->toLocationId; }

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
