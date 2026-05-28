<?php

namespace InventoryApp\Domain\Inventory\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use DateTimeImmutable;

/**
 * Fired after any stock mutation leaves total stock at or below the
 * product's reorder threshold. Consumers (e.g., notification services,
 * purchasing workflows) can subscribe to trigger reorder alerts.
 */
final class LowStockDetected implements DomainEvent
{
    public function __construct(
        public readonly SKU    $sku,
        public readonly int    $currentQuantity,
        public readonly int    $threshold,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function getSku(): SKU { return $this->sku; }

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
