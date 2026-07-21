<?php

namespace InventoryApp\Domain\Returns\Aggregates;

use InventoryApp\Domain\Shared\Entities\AggregateRoot;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InventoryApp\Domain\Returns\Entities\RMAItem;
use DateTimeImmutable;
use InvalidArgumentException;

class RMA extends AggregateRoot
{
    private string $id;
    private string $rmaNumber;
    private TenantId $tenantId;
    private string $customerId;
    private LocationId $locationId;
    private RMAStatus $status;
    /** @var RMAItem[] */
    private array $items;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $rmaNumber,
        TenantId $tenantId,
        string $customerId,
        LocationId $locationId,
        RMAStatus $status,
        array $items,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->rmaNumber = $rmaNumber;
        $this->tenantId = $tenantId;
        $this->customerId = $customerId;
        $this->locationId = $locationId;
        $this->status = $status;
        $this->items = $items;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getRmaNumber(): string { return $this->rmaNumber; }
    public function getTenantId(): TenantId { return $this->tenantId; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getLocationId(): LocationId { return $this->locationId; }
    public function getStatus(): RMAStatus { return $this->status; }
    /** @return RMAItem[] */
    public function getItems(): array { return $this->items; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function authorize(): void
    {
        if ($this->status !== RMAStatus::Requested) {
            throw new InvalidArgumentException("RMA must be in Requested status to be authorized.");
        }
        $this->status = RMAStatus::Authorized;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function receiveItem(string $variantId, int $amount, RMADisposition $disposition): void
    {
        if ($this->status !== RMAStatus::Authorized && $this->status !== RMAStatus::Received) {
            throw new InvalidArgumentException("RMA must be in Authorized or Received status to receive items.");
        }

        $found = false;
        foreach ($this->items as $item) {
            if ($item->getVariantId() === $variantId) {
                $item->receive($amount, $disposition);
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new InvalidArgumentException("Item with variant ID {$variantId} not found in RMA.");
        }

        $allProcessed = true;
        foreach ($this->items as $item) {
            if (!$item->isFullyProcessed()) {
                $allProcessed = false;
                break;
            }
        }

        $this->status = $allProcessed ? RMAStatus::Completed : RMAStatus::Received;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function reject(): void
    {
        if ($this->status !== RMAStatus::Requested) {
            throw new InvalidArgumentException("RMA must be in Requested status to be rejected.");
        }
        foreach ($this->items as $item) {
            $item->reject();
        }
        $this->status = RMAStatus::Rejected;
        $this->updatedAt = new DateTimeImmutable();
    }
}
