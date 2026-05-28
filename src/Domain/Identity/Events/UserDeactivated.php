<?php

namespace InventoryApp\Domain\Identity\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use DateTimeImmutable;

/**
 * Fired when a user account is deactivated.
 * Consumers: token revocation, access log, notification service.
 */
final class UserDeactivated implements DomainEvent
{
    public function __construct(
        public readonly string   $userId,
        public readonly TenantId $tenantId,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
