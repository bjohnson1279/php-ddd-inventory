<?php

namespace InventoryApp\Domain\Rfid;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

class RfidScanProcessedEvent implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $locationId,
        public readonly int $totalCount,
        public readonly int $matchedCount,
        public readonly int $unmatchedCount,
        public readonly array $unmatchedEpcs
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
