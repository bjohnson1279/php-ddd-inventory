<?php

namespace InventoryApp\Domain\Barcode\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class BarcodeRevoked implements DomainEvent
{
    public function __construct(
        public readonly string $variantId,
        public readonly string $barcodeValue,
        public readonly string $symbology,
        private readonly DateTimeImmutable $occurredOn
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
