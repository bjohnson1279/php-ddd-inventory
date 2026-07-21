<?php

namespace InventoryApp\Application\Procurement\UseCases;

use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use Ramsey\Uuid\Uuid;
use Exception;

class CreatePurchaseOrder
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepository
    ) {}

    public function execute(array $data): PurchaseOrder
    {
        $existing = $this->poRepository->findByNumber($data['purchaseOrderNumber']);
        if ($existing) {
            throw new Exception("Purchase order with number {$data['purchaseOrderNumber']} already exists.");
        }

        $items = [];
        foreach ($data['items'] as $itemData) {
            $items[] = new PurchaseOrderItem(
                Uuid::uuid4()->toString(),
                $itemData['variantId'],
                $itemData['quantity'],
                $itemData['unitCostCents'],
                0 // receivedQuantity
            );
        }

        $po = new PurchaseOrder(
            Uuid::uuid4()->toString(),
            $data['purchaseOrderNumber'],
            $data['vendorId'],
            $data['tenantId'],
            $data['locationId'],
            PurchaseOrderStatus::Draft,
            $items
        );

        $this->poRepository->save($po);
        return $po;
    }
}
