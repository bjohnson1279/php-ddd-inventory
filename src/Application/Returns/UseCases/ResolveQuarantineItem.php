<?php

namespace InventoryApp\Application\Returns\UseCases;

use InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\CostLayerService;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Exception;

class ResolveQuarantineItem
{
    private readonly CostLayerService $costLayerService;

    public function __construct(
        private readonly QuarantineRepositoryInterface $quarantineRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CostLayerRepositoryInterface $costLayerRepository,
        private readonly AccountingJournalService $journalService
    ) {
        $this->costLayerService = new CostLayerService($costLayerRepository);
    }

    public function execute(array $dto): void
    {
        $qItem = $this->quarantineRepository->findById($dto['quarantineItemId']);
        if (!$qItem) {
            throw new Exception("Quarantine item with ID {$dto['quarantineItemId']} not found.");
        }

        $variantId = $qItem->getVariantId();
        $quarantineLocId = $qItem->getLocationId()->getValue() . '-quarantine';

        // Decrement stock from quarantine location
        $product = $this->productRepository->findById($variantId);
        if (!$product) {
            throw new Exception("Product not found for variant {$variantId}");
        }
        $product->dispatchStockAt(new LocationId($quarantineLocId), new Quantity($qItem->getQuantity()), "RESOLVE-Q-{$qItem->getId()}");
        $this->productRepository->save($product);

        if ($dto['resolution'] === 'RESTOCK') {
            $qItem->resolveRestock();

            // Increment main location stock
            $product->receiveStockAt($qItem->getLocationId(), new Quantity($qItem->getQuantity()), "RESOLVE-Q-{$qItem->getId()}");
            $this->productRepository->save($product);
        } elseif ($dto['resolution'] === 'SCRAP') {
            $qItem->resolveScrap();

            // Consume cost layer
            $costBreakdown = $this->costLayerService->consumeFifoLayers($variantId, $qItem->getQuantity());

            // Post write-off journal entry
            $this->journalService->onInventoryWriteOff(
                $qItem->getTenantId()->getValue(),
                $qItem->getId(),
                $costBreakdown->totalCostCents,
                new \DateTimeImmutable()
            );
        } elseif ($dto['resolution'] === 'RTV') {
            $qItem->resolveRtv();

            // Consume cost layer
            $costBreakdown = $this->costLayerService->consumeFifoLayers($variantId, $qItem->getQuantity());

            // Post Return to Vendor journal entry
            $this->journalService->onReturnToVendor(
                $qItem->getTenantId()->getValue(),
                $qItem->getId(),
                $costBreakdown->totalCostCents,
                new \DateTimeImmutable()
            );
        } else {
            throw new \InvalidArgumentException("Invalid resolution type: {$dto['resolution']}");
        }

        $this->quarantineRepository->save($qItem);
    }
}
