<?php

namespace InventoryApp\Domain\Inventory\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use DateTimeImmutable;

/**
 * Fired by InventoryService when the ledger-based decrement path is used
 * (i.e., sale or kit-sale via the InventoryService domain service rather
 * than directly through the Product aggregate).
 *
 * NOTE: This event intentionally carries a variantId string rather than a
 * SKU value object because the ledger operates at the variant level and
 * may pre-date a full Product being loaded. Listeners that need SKU context
 * should look up the Product via the ProductRepository.
 */
final class StockDecremented implements DomainEvent
{
    public function __construct(
        public readonly string     $variantId,
        public readonly int        $quantity,      // absolute positive value; direction implied by event type
        public readonly ReasonCode $reason,
        public readonly string     $actorId,
        public readonly ?string    $referenceId,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
