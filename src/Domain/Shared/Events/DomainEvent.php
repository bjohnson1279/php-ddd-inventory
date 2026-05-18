<?php

namespace InventoryApp\Domain\Shared\Events;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredOn(): DateTimeImmutable;
}
