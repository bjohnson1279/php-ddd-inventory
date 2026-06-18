<?php

namespace InventoryApp\Domain\Procurement\Services;

use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Events\ReorderPointReachedEvent;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Psr\EventDispatcher\EventDispatcherInterface;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class ReorderPolicyService
{
    public function __construct(
        private readonly ReorderPolicyRepositoryInterface $reorderPolicyRepository,
        private readonly PurchaseOrderRepositoryInterface $poRepository,
        private readonly EventDispatcherInterface $events
    ) {}

    public function checkPolicy(
        string $skuStr,
        string $locationId,
        int $currentQuantity,
        string $tenantId = 'default-tenant'
    ): void {
        $sku = new SKU($skuStr);
        $policy = $this->reorderPolicyRepository->findBySkuAndLocation($sku, $locationId);
        if (!$policy) {
            return;
        }

        if ($policy->shouldReorder($currentQuantity)) {
            // 1. Dispatch Event
            $event = new ReorderPointReachedEvent(
                $skuStr,
                $locationId,
                $currentQuantity,
                $policy->reorderPoint,
                $policy->reorderQuantity,
                new DateTimeImmutable()
            );
            $this->events->dispatch($event);

            // 2. Check if a draft/approved/sent purchase order already exists for this vendor/location and includes this sku
            $allPos = $this->poRepository->findAll();
            $alreadyOrdered = false;
            foreach ($allPos as $po) {
                if ($po->tenantId !== $tenantId || $po->locationId !== $locationId) {
                    continue;
                }
                if (
                    $po->getStatus() === PurchaseOrderStatus::Draft ||
                    $po->getStatus() === PurchaseOrderStatus::Approved ||
                    $po->getStatus() === PurchaseOrderStatus::Sent
                ) {
                    foreach ($po->getItems() as $item) {
                        if ($item->variantId === $skuStr && $item->getReceivedQuantity() < $item->quantity) {
                            $alreadyOrdered = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$alreadyOrdered) {
                // Automatically create a draft purchase order!
                $poNumber = 'AUTO-REORDER-' . $skuStr . '-' . strtoupper(base_convert((string)time(), 10, 36));
                $poId = Uuid::uuid4()->toString();
                $itemId = Uuid::uuid4()->toString();
                
                $item = new PurchaseOrderItem(
                    $itemId,
                    $skuStr,
                    $policy->reorderQuantity,
                    0, // unitCostCents
                    0  // receivedQuantity
                );

                $po = new PurchaseOrder(
                    $poId,
                    $poNumber,
                    'AUTO-SYSTEM-VENDOR',
                    $tenantId,
                    $locationId,
                    PurchaseOrderStatus::Draft,
                    [$item]
                );

                $this->poRepository->save($po);
            }
        }
    }
}
