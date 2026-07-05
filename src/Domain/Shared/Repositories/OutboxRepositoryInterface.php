<?php

namespace InventoryApp\Domain\Shared\Repositories;

use InventoryApp\Domain\Shared\Events\DomainEvent;

interface OutboxRepositoryInterface
{
    /**
     * Save an event to the outbox. Can accept a DomainEvent object or a pre-formatted array/associative array.
     */
    public function save(DomainEvent|array $event): void;

    /**
     * Fetch pending outbox events.
     * @return \InventoryApp\Domain\Shared\Entities\OutboxEvent[]
     */
    public function fetchPending(int $limit, int $maxAttempts = 5): array;

    public function markProcessed(string $id): void;

    public function markFailed(string $id, string $error): void;

    /**
     * Fetch dead lettered outbox events.
     * @return \InventoryApp\Domain\Shared\Entities\OutboxEvent[]
     */
    public function fetchDeadLettered(int $limit, int $maxAttempts = 5): array;

    public function retryEvent(string $id): void;

    /**
     * Fetch outbox statistics.
     */
    public function fetchStats(int $maxAttempts = 5): array;
}
