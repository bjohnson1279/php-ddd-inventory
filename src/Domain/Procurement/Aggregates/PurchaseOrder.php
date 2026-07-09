<?php

namespace InventoryApp\Domain\Procurement\Aggregates;

use InventoryApp\Domain\Shared\Entities\AggregateRoot;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use DomainException;

class PurchaseOrder extends AggregateRoot
{
    private PurchaseOrderStatus $status;
    private array $items;

    public function __construct(
        public readonly string $id,
        public readonly string $purchaseOrderNumber,
        public readonly string $vendorId,
        public readonly string $tenantId,
        public readonly string $locationId,
        PurchaseOrderStatus $status = PurchaseOrderStatus::Draft,
        array $items = [],
        public readonly ?\DateTimeInterface $createdAt = null,
        public readonly ?\DateTimeInterface $updatedAt = null
    ) {
        $this->status = $status;
        $this->items = $items;
    }

    public function getStatus(): PurchaseOrderStatus
    {
        return $this->status;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function approve(): void
    {
        if ($this->status !== PurchaseOrderStatus::Draft) {
            throw new DomainException("Only draft purchase orders can be approved.");
        }
        $this->status = PurchaseOrderStatus::Approved;
    }

    public function send(): void
    {
        if ($this->status !== PurchaseOrderStatus::Approved) {
            throw new DomainException("Only approved purchase orders can be sent.");
        }
        $this->status = PurchaseOrderStatus::Sent;
    }

    public function receiveItems(string $variantId, int $quantity): void
    {
        if (
            $this->status !== PurchaseOrderStatus::Sent &&
            $this->status !== PurchaseOrderStatus::PartiallyReceived
        ) {
            throw new DomainException("Can only receive items on Sent or Partially Received purchase orders.");
        }

        $item = null;
        foreach ($this->items as $i) {
            if ($i->variantId === $variantId) {
                $item = $i;
                break;
            }
        }

        if (!$item) {
            throw new DomainException("Item with variant ID {$variantId} not found in this purchase order.");
        }

        $item->receive($quantity);

        // Update status
        $allFullyReceived = true;
        foreach ($this->items as $i) {
            if (!$i->isFullyReceived()) {
                $allFullyReceived = false;
                break;
            }
        }

        if ($allFullyReceived) {
            $this->status = PurchaseOrderStatus::Received;
        } else {
            $this->status = PurchaseOrderStatus::PartiallyReceived;
        }
    }

    public function close(): void
    {
        $this->status = PurchaseOrderStatus::Closed;
    }
}
