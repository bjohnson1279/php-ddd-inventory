<?php

namespace InventoryApp\Domain\Returns\Aggregates;

use InventoryApp\Domain\Shared\Entities\AggregateRoot;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Returns\Enums\QuarantineStatus;
use DateTimeImmutable;
use InvalidArgumentException;

class QuarantineItem extends AggregateRoot
{
    private string $id;
    private string $variantId;
    private int $quantity;
    private string $reason;
    private LocationId $locationId;
    private TenantId $tenantId;
    private QuarantineStatus $status;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $resolvedAt;

    public function __construct(
        string $id,
        string $variantId,
        int $quantity,
        string $reason,
        LocationId $locationId,
        TenantId $tenantId,
        QuarantineStatus $status = QuarantineStatus::Quarantined,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $resolvedAt = null
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity must be greater than zero.");
        }

        $this->id = $id;
        $this->variantId = $variantId;
        $this->quantity = $quantity;
        $this->reason = $reason;
        $this->locationId = $locationId;
        $this->tenantId = $tenantId;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->resolvedAt = $resolvedAt;
    }

    public function getId(): string { return $this->id; }
    public function getVariantId(): string { return $this->variantId; }
    public function getQuantity(): int { return $this->quantity; }
    public function getReason(): string { return $this->reason; }
    public function getLocationId(): LocationId { return $this->locationId; }
    public function getTenantId(): TenantId { return $this->tenantId; }
    public function getStatus(): QuarantineStatus { return $this->status; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getResolvedAt(): ?DateTimeImmutable { return $this->resolvedAt; }

    public function resolveRestock(): void
    {
        if ($this->status !== QuarantineStatus::Quarantined) {
            throw new InvalidArgumentException("Quarantine item is already resolved.");
        }
        $this->status = QuarantineStatus::Restocked;
        $this->resolvedAt = new DateTimeImmutable();
    }

    public function resolveScrap(): void
    {
        if ($this->status !== QuarantineStatus::Quarantined) {
            throw new InvalidArgumentException("Quarantine item is already resolved.");
        }
        $this->status = QuarantineStatus::Scrapped;
        $this->resolvedAt = new DateTimeImmutable();
    }

    public function resolveRtv(): void
    {
        if ($this->status !== QuarantineStatus::Quarantined) {
            throw new InvalidArgumentException("Quarantine item is already resolved.");
        }
        $this->status = QuarantineStatus::Rtv;
        $this->resolvedAt = new DateTimeImmutable();
    }
}
