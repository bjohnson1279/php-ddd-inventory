<?php

namespace InventoryApp\Domain\Serial\Aggregates;

use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use InventoryApp\Domain\Serial\Events\SerialStatusChanged;

final class StatusTransition
{
    public function __construct(
        public readonly SerializedItemStatus $from,
        public readonly SerializedItemStatus $to,
        public readonly string $reason,
        public readonly string $actorId,
        public readonly ?string $referenceId,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}

class SerializedItem
{
    private SerializedItemStatus $status;
    private array $history      = [];
    private array $domainEvents = [];

    public function __construct(
        public readonly string               $id,
        public readonly string               $variantId,
        public readonly SerialNumber         $serialNumber,
        public readonly string               $tenantId,
        private string                       $locationId,
        SerializedItemStatus                 $initialStatus = SerializedItemStatus::Pending,
    ) {
        $this->status = $initialStatus;
    }

    public function receive(string $location, string $actorId, string $purchaseOrderId): void
    {
        $this->transitionTo(SerializedItemStatus::InStock, "Received against PO {$purchaseOrderId}", $actorId, $purchaseOrderId);
        $this->locationId = $location;
    }

    public function sell(string $saleId, string $actorId): void
    {
        $this->transitionTo(SerializedItemStatus::Sold, "Sold — sale {$saleId}", $actorId, $saleId);
    }

    public function acceptReturn(string $returnId, string $actorId): void
    {
        $this->transitionTo(SerializedItemStatus::Returned, "Customer return — {$returnId}", $actorId, $returnId);
    }

    public function restock(string $actorId, string $returnId): void
    {
        $this->transitionTo(SerializedItemStatus::InStock, "Restocked after inspection — return {$returnId}", $actorId, $returnId);
    }

    public function writeOff(string $reason, string $actorId, ?string $referenceId = null): void
    {
        $this->transitionTo(SerializedItemStatus::WrittenOff, $reason, $actorId, $referenceId);
    }

    public function quarantine(string $reason, string $actorId, ?string $referenceId = null): void
    {
        $this->transitionTo(SerializedItemStatus::Quarantined, $reason, $actorId, $referenceId);
    }

    public function status(): SerializedItemStatus     { return $this->status; }
    public function locationId(): string               { return $this->locationId; }
    public function isAvailable(): bool                { return $this->status === SerializedItemStatus::InStock; }
    public function history(): array                   { return $this->history; }
    public function lastTransition(): ?StatusTransition { return !empty($this->history) ? end($this->history) : null; }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function transitionTo(
        SerializedItemStatus $target,
        string $reason,
        string $actorId,
        ?string $referenceId,
    ): void {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(sprintf(
                'Invalid serial status transition from %s to %s',
                $this->status->value,
                $target->value,
            ));
        }

        $now        = new \DateTimeImmutable();
        $transition = new StatusTransition($this->status, $target, $reason, $actorId, $referenceId, $now);
        $this->history[] = $transition;

        $this->domainEvents[] = new SerialStatusChanged(
            serializedItemId: $this->id,
            variantId:        $this->variantId,
            serialNumber:     $this->serialNumber->value,
            locationId:       $this->locationId,
            from:             $this->status,
            to:               $target,
            reason:           $reason,
            actorId:          $actorId,
            referenceId:      $referenceId,
            occurredOn:       $now,
        );

        $this->status = $target;
    }
}
