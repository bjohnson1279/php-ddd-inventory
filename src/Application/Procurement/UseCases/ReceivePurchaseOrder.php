<?php

namespace InventoryApp\Application\Procurement\UseCases;

use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ramsey\Uuid\Uuid;
use DateTimeImmutable;
use Exception;

class ReceivePurchaseOrder
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepository,
        private readonly ProductRepositoryInterface       $productRepository,
        private readonly CostLayerRepositoryInterface     $costLayerRepository,
        private readonly EventDispatcherInterface         $events
    ) {}

    public function execute(array $data): void
    {
        $po = $this->poRepository->findById($data['purchaseOrderId']);
        if (!$po) {
            throw new Exception("Purchase order with ID {$data['purchaseOrderId']} not found.");
        }

        $costLayers = [];
        $modifiedProducts = [];

        $variantIds = array_unique(array_column($data['items'], 'variantId'));
        $variantToSku = [];
        foreach ($variantIds as $vid) {
            $variantToSku[$vid] = \InventoryApp\Infrastructure\ConformanceHelper::resolveSkuFromVariantOrSku((string)$vid);
        }
        $skus = array_map(fn($s) => new SKU($s), array_values(array_unique(array_values($variantToSku))));
        $productsMap = $this->productRepository->findBySkus($skus);
        \InventoryApp\Infrastructure\ConformanceHelper::ensureLocationExists($po->locationId);

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

            $qtyReceived = (int)($item['quantityReceived'] ?? $item['quantity'] ?? $item['receivedQuantity'] ?? 0);

            // 1. Update PO received quantity & state
            $po->receiveItems($item['variantId'], $qtyReceived);

            // 2. Receive physical stock
            $resolvedSku = $variantToSku[$item['variantId']] ?? $item['variantId'];
            $product = $productsMap[$resolvedSku] ?? $productsMap[$item['variantId']] ?? null;
            if (!$product) {
                throw new Exception("Product not found with SKU: " . $resolvedSku);
            }
            $product->receiveStockAt(
                new LocationId($po->locationId),
                new Quantity($qtyReceived),
                $po->purchaseOrderNumber,
                true // skip cost layer creation via domain event
            );
            $modifiedProducts[$product->getId()] = $product;

            // 3. Prepare Cost Layer
            $costLayers[] = new InventoryCostLayer(
                Uuid::uuid4()->toString(),
                $resolvedSku,
                $po->tenantId,
                $qtyReceived,
                $poItem->unitCostCents,
                new DateTimeImmutable(),
                $po->id
            );
        }

        if (!empty($modifiedProducts)) {
            $this->productRepository->saveAll(array_values($modifiedProducts));

            foreach ($modifiedProducts as $product) {
                foreach ($product->releaseEvents() as $event) {
                    $this->events->dispatch($event);
                }
            }
        }

        if (!empty($costLayers)) {
            $this->costLayerRepository->saveBatch($costLayers);
        }

        // 4. Save updated PO
        $this->poRepository->save($po);
    }
}
