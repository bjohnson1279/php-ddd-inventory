<?php

namespace InventoryApp\Application\Procurement\UseCases;

use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Application\Inventory\Factories\ReceiveStockFactoryInterface;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Ramsey\Uuid\Uuid;
use DateTimeImmutable;
use Exception;

class ReceivePurchaseOrder
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepository,
        private readonly CostLayerRepositoryInterface     $costLayerRepository,
        private readonly ReceiveStockFactoryInterface     $receiveStockFactory
    ) {}

    public function execute(array $data): void
    {
        $po = $this->poRepository->findById($data['purchaseOrderId']);
        if (!$po) {
            throw new Exception("Purchase order with ID {$data['purchaseOrderId']} not found.");
        }

        $receiveStock = $this->receiveStockFactory->create();
        $costLayers = [];

        foreach ($data['items'] as $item) {
            $poItem = null;
            foreach ($po->getItems() as $i) {
                if ($i->variantId === $item['variantId']) {
                    $poItem = $i;
                    break;
                }
            }

            if (!$poItem) {
                throw new Exception("Item {$item['variantId']} not found in purchase order {$po->purchaseOrderNumber}.");
            }

            // 1. Update PO received quantity & state
            $po->receiveItems($item['variantId'], $item['quantityReceived']);

            // 2. Receive physical stock
            $receiveStock->execute(
                new SKU($item['variantId']),
                new LocationId($po->locationId),
                new Quantity($item['quantityReceived']),
                $po->purchaseOrderNumber
            );

            // 3. Prepare Cost Layer
            $costLayers[] = new InventoryCostLayer(
                Uuid::uuid4()->toString(),
                $item['variantId'],
                $po->tenantId,
                $item['quantityReceived'],
                $poItem->unitCostCents,
                new DateTimeImmutable(),
                $po->id
            );
        }

        if (!empty($costLayers)) {
            $this->costLayerRepository->saveBatch($costLayers);
        }

        // 4. Save updated PO
        $this->poRepository->save($po);
    }
}
