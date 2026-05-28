<?php

namespace InventoryApp\Domain\Serial\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use DateTimeImmutable;

/**
 * Fired every time a SerializedItem transitions between statuses.
 * Consumers: ledger writer (when entering/leaving InStock), audit log.
 *
 * Use SerializedItemStatus::requiresLedgerEntry() to determine whether
 * this transition affects the countable stock position.
 */
final class SerialStatusChanged implements DomainEvent
{
    public function __construct(
        public readonly string               $serializedItemId,
        public readonly string               $variantId,
        public readonly string               $serialNumber,
        public readonly string               $locationId,
        public readonly SerializedItemStatus $from,
        public readonly SerializedItemStatus $to,
        public readonly string               $reason,
        public readonly string               $actorId,
        public readonly ?string              $referenceId,
        private readonly DateTimeImmutable   $occurredOn,
    ) {}

    public function requiresLedgerEntry(): bool
    {
        return $this->to->requiresLedgerEntry($this->from);
    }

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
