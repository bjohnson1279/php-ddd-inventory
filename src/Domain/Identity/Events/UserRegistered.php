<?php

namespace InventoryApp\Domain\Identity\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use DateTimeImmutable;

/**
 * Fired when a new user registers within a tenant.
 * Consumers: welcome-email service, audit log, analytics.
 */
final class UserRegistered implements DomainEvent
{
    public function __construct(
        public readonly string   $userId,
        public readonly TenantId $tenantId,
        public readonly string   $email,
        public readonly string   $name,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
