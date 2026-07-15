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

class DisassembleKit
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
            throw new Exception("Quantity to disassemble must be greater than zero.");
        }

        // 1. Resolve kit details
        $kit = $this->kitRepository->findBySku($kitSkuStr);
        if (!$kit) {
            throw new Exception("Kit with SKU {$kitSkuStr} not found.");
        }

        // 2. Resolve kit's product variant
        $kitProduct = $this->productRepository->findBySku(new SKU($kitSkuStr));
        if (!$kitProduct) {
            throw new Exception("Product variant for Kit SKU {$kitSkuStr} not found.");
        }

        // 3. Validate kit stock level
        $availableKitStock = $this->ledgerRepository->currentQuantity($kitProduct->getId());
        if ($availableKitStock < $quantity) {
            throw new Exception("Insufficient stock for Kit variant {$kitSkuStr}. Needed: {$quantity}, Available: {$availableKitStock}");
        }

        // 4. Consume FIFO costing layers for the kit variant
        $kitBreakdown = $this->costLayerService->consumeFifoLayers($kitProduct->getId(), $quantity);
        $totalDisassembledCost = $kitBreakdown->totalCostCents;

        // 5. Decrement kit stock on Product aggregate
        $kitProduct->dispatchStockAt(new LocationId($locationId), new Quantity($quantity), $referenceId);
        $this->productRepository->save($kitProduct);

        // 6. Write deduction ledger entry for kit variant
        $kitLedgerEntry = new LedgerEntry(
            id: Uuid::uuid4()->toString(),
            variantId: $kitProduct->getId(),
            quantity: -$quantity,
            reason: ReasonCode::KitDisassembly,
            actorId: $actorId,
            referenceId: $referenceId,
            occurredAt: new \DateTimeImmutable(),
            metadata: ['locationId' => $locationId]
        );
        $this->ledgerRepository->append($kitLedgerEntry);

        // 7. Estimate components average cost and distribute cost proportionally
        $totalEstimatedComponentsCost = 0;
        $componentAvgCosts = [];

        foreach ($kit->components() as $component) {
            $needed = $component->quantity * $quantity;
            $avgUnitCost = 0;

            try {
                $activeLayers = $this->costLayerRepository->getActiveLayers($component->variantId, 'received_at ASC');
                $totalUnits = 0;
                $totalValue = 0;
                foreach ($activeLayers as $layer) {
                    $totalUnits += $layer->remainingQuantity();
                    $totalValue += $layer->remainingQuantity() * $layer->unitCostCents;
                }
                if ($totalUnits > 0) {
                    $avgUnitCost = (int) round($totalValue / $totalUnits);
                } else {
                    $avgUnitCost = !empty($activeLayers) ? $activeLayers[0]->unitCostCents : 1000;
                }
            } catch (\Exception $e) {
                $avgUnitCost = 1000; // fallback default
            }

            $componentAvgCosts[] = [
                'variantId' => $component->variantId,
                'quantity' => $needed,
                'avgUnitCost' => $avgUnitCost
            ];
            $totalEstimatedComponentsCost += $needed * $avgUnitCost;
        }

        $scaleFactor = $totalEstimatedComponentsCost > 0 ? $totalDisassembledCost / $totalEstimatedComponentsCost : 0;

        // 8. Restore component variants stock and costing layers
        $componentVariantIds = array_column($componentAvgCosts, 'variantId');
        $prefetchedProducts = $this->productRepository->findByIds($componentVariantIds);

        $layersToSave = [];
        $ledgerEntriesToSave = [];
        $productsToSave = [];

        foreach ($componentAvgCosts as $item) {
            $allocatedUnitCost = $scaleFactor > 0 ? (int) round($item['avgUnitCost'] * $scaleFactor) : 0;

            // Add new costing layer for restored component
            $layer = new InventoryCostLayer(
                id: Uuid::uuid4()->toString(),
                variantId: $item['variantId'],
                tenantId: $tenantId,
                originalQuantity: $item['quantity'],
                unitCostCents: $allocatedUnitCost,
                receivedAt: new \DateTimeImmutable(),
                purchaseOrderId: $referenceId
            );
            $layersToSave[] = $layer;

            // Increment stock level on Product aggregate root
            $compProduct = $prefetchedProducts[$item['variantId']] ?? null;
            if (!$compProduct) {
                throw new Exception("Product variant {$item['variantId']} not found.");
            }
            $compProduct->receiveStockAt(new LocationId($locationId), new Quantity($item['quantity']), $referenceId);
            $productsToSave[] = $compProduct;

            // Add increment ledger entry for this component
            $ledgerEntry = new LedgerEntry(
                id: Uuid::uuid4()->toString(),
                variantId: $item['variantId'],
                quantity: $item['quantity'],
                reason: ReasonCode::KitDisassembly,
                actorId: $actorId,
                referenceId: $referenceId,
                occurredAt: new \DateTimeImmutable(),
                metadata: ['locationId' => $locationId]
            );
            $ledgerEntriesToSave[] = $ledgerEntry;
        }

        if (!empty($layersToSave)) {
            $this->costLayerRepository->saveBatch($layersToSave);
        }
        if (!empty($productsToSave)) {
            $this->productRepository->saveAll($productsToSave);
        }
        if (!empty($ledgerEntriesToSave)) {
            $this->ledgerRepository->appendAll($ledgerEntriesToSave);
        }

        // 9. Post journal entries if Accrual
        $this->journalService->onKitDisassembly(
            $tenantId,
            new \DateTimeImmutable(),
            $kitSkuStr,
            $totalDisassembledCost,
            $referenceId
        );
    }
}
