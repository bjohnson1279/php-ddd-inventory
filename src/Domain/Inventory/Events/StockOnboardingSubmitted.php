<?php

namespace InventoryApp\Domain\Inventory\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

final class StockOnboardingSubmitted implements DomainEvent
{
    public function __construct(
        public readonly string $onboardingId,
        public readonly string $tenantId,
        public readonly string $locationId,
        public readonly DateTimeImmutable $asOfDate,
        private readonly DateTimeImmutable $occurredOn
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
