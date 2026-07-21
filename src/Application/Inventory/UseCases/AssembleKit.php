<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\CostLayerService;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;
use Ramsey\Uuid\Uuid;

class AssembleKit
{
    private readonly CostLayerService $costLayerService;

    public function __construct(
        private readonly KitRepositoryInterface $kitRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LedgerRepositoryInterface $ledgerRepository,
        private readonly CostLayerRepositoryInterface $costLayerRepository,
        private readonly AccountingJournalService $journalService
    ) {
        $this->costLayerService = new CostLayerService($costLayerRepository);
    }

    public function execute(array $dto): void
    {
        $tenantId = $dto['tenantId'];
        $locationId = $dto['locationId'];
        $kitSkuStr = $dto['kitSku'];
        $quantity = $dto['quantity'];
        $actorId = $dto['actorId'];
        $referenceId = $dto['referenceId'];

        if ($quantity <= 0) {
            throw new Exception("Quantity to assemble must be greater than zero.");
        }

        // 1. Resolve kit details
        $kit = $this->kitRepository->findBySku($kitSkuStr);
        if (!$kit) {
            throw new Exception("Kit with SKU {$kitSkuStr} not found.");
        }

        $kitProduct = $this->productRepository->findBySku(new SKU($kitSkuStr));
        if (!$kitProduct) {
            throw new Exception("Product variant for Kit SKU {$kitSkuStr} not found.");
        }

        // 2. Validate component stock level and pre-fetch products (Optimized N+1 queries)
        $componentVariantIds = [];
        foreach ($kit->components() as $component) {
            $componentVariantIds[] = $component->variantId;
        }

        $availableQuantities = $this->ledgerRepository->currentQuantities($componentVariantIds);
        $productsMap = $this->productRepository->findByIds($componentVariantIds);

        $componentsToConsume = [];
            $needed = $component->quantity * $quantity;
            $available = $availableQuantities[$component->variantId] ?? 0;
            if ($available < $needed) {
                throw new Exception("Insufficient stock for component variant ID {$component->variantId}. Needed: {$needed}, Available: {$available}");
            }

            $product = $productsMap[$component->variantId] ?? null;
            if (!$product) {
                throw new Exception("Product variant {$component->variantId} not found.");
            }

            $componentsToConsume[] = [
                'variantId' => $component->variantId,
                'needed' => $needed,
                'product' => $product
            ];
        }

        // 3. Consume FIFO costing layers for components and calculate total components cost
        $totalCostCents = 0;
        $modifiedProducts = [];
        $ledgerEntriesToAppend = [];
        foreach ($componentsToConsume as $comp) {
            $breakdown = $this->costLayerService->consumeFifoLayers($comp['variantId'], $comp['needed']);
            $totalCostCents += $breakdown->totalCostCents;

            // Deduct stock on Product aggregate root
            $product = $comp['product'];
            $product->dispatchStockAt(new LocationId($locationId), new Quantity($comp['needed']), $referenceId);
            $modifiedProducts[] = $product;

            // Write deduction to ledger_entries
            $ledgerEntriesToAppend[] = new LedgerEntry(
                id: Uuid::uuid4()->toString(),
                variantId: $comp['variantId'],
                quantity: -$comp['needed'],
                reason: ReasonCode::KitAssembly,
                actorId: $actorId,
                referenceId: $referenceId,
                occurredAt: new \DateTimeImmutable(),
                metadata: ['locationId' => $locationId]
            );
        }

        // Save all modified products collectively (Optimized write)
        if (!empty($modifiedProducts)) {
            $this->productRepository->saveAll($modifiedProducts);
        }

        // 4. Calculate assembled unit cost
        $unitCostCents = (int) round($totalCostCents / $quantity);

        // 5. Create new costing layer for the assembled Kit variant

        $kitLayer = new InventoryCostLayer(
            id: Uuid::uuid4()->toString(),
            variantId: $kitProduct->getId(),
            tenantId: $tenantId,
            originalQuantity: $quantity,
            unitCostCents: $unitCostCents,
            receivedAt: new \DateTimeImmutable(),
            purchaseOrderId: $referenceId
        );
        $this->costLayerRepository->save($kitLayer);

        // 6. Increment stock for Kit variant on Product aggregate
        $kitProduct->receiveStockAt(new LocationId($locationId), new Quantity($quantity), $referenceId);
        $this->productRepository->save($kitProduct);

        // 7. Write increment ledger entry for Kit variant
        $ledgerEntriesToAppend[] = new LedgerEntry(
            quantity: $quantity,
            reason: ReasonCode::KitAssembly,
            actorId: $actorId,
            referenceId: $referenceId,
            occurredAt: new \DateTimeImmutable(),
            metadata: ['locationId' => $locationId]
        $this->ledgerRepository->appendAll($ledgerEntriesToAppend);

        // 8. Write balanced double-entry Journal Entry
        $this->journalService->onKitAssembly(
            $tenantId,
            new \DateTimeImmutable(),
            $kitSkuStr,
            $totalCostCents,
            $referenceId
    }
}



{

    }

    {

        }

        }

        }

        }


            }

            }

        }



            $ledgerEntry = new LedgerEntry(
            $this->ledgerRepository->append($ledgerEntry);
        }

        }





        $kitLedgerEntry = new LedgerEntry(
        $this->ledgerRepository->append($kitLedgerEntry);

    }
}
