<?php

namespace InventoryApp\Domain\Barcode\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class BarcodeAssigned implements DomainEvent
{
    public function __construct(
        public readonly string $variantId,
        public readonly string $barcodeValue,
        public readonly string $symbology,
        public readonly string $source,
        public readonly bool $isPrimary,
        private readonly DateTimeImmutable $occurredOn
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
