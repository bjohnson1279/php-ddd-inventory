<?php

namespace InventoryApp\Domain\Shared\Entities;

use DateTimeImmutable;

class OutboxEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $eventName,
        public readonly string $payload,
        public readonly DateTimeImmutable $occurredOn,
        public readonly ?DateTimeImmutable $processedAt = null,
        public readonly int $attempts = 0,
        public readonly ?string $lastError = null,
        public readonly ?DateTimeImmutable $nextAttemptAt = null
    ) {}
}
