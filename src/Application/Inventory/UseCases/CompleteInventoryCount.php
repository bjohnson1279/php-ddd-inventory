<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class CompleteInventoryCount
{
    public function __construct(
        private readonly InventoryCountRepositoryInterface $countRepository,
        private readonly ProductRepositoryInterface        $productRepository,
        private readonly EventDispatcherInterface          $events,
    ) {}

    public function execute(string $countId): void
    {
        $inventoryCount = $this->countRepository->findById($countId);

        if (!$inventoryCount) {
            throw new Exception("Inventory count not found: " . $countId);
        }

        // Mark the count as completed
        $inventoryCount->complete();
        $this->countRepository->save($inventoryCount);

        // Reconcile physical counted stock to the actual Products

        // Batch load products
        $skus = [];
        foreach ($inventoryCount->getItems() as $item) {
            $skus[] = $item->getSku();
        }

        $productsBySku = $this->productRepository->findBySkus($skus);
        $productsToSave = [];
        $eventsToDispatch = [];

        foreach ($inventoryCount->getItems() as $item) {
            $skuValue = $item->getSku()->getValue();
            if (isset($productsBySku[$skuValue])) {
                $product = $productsBySku[$skuValue];

                $product->reconcileStockAt(
                    $item->getLocationId(),
                    $item->getCountedQuantity(),
                    'COUNT_' . $inventoryCount->getId()
                );

                // Group by ID to avoid duplicate saves/transactions if the same SKU appears multiple times
                $productsToSave[$product->getId()] = $product;

                foreach ($product->releaseEvents() as $event) {
                    $eventsToDispatch[] = $event;
                }
            }
        }

        if (!empty($productsToSave)) {
            $this->productRepository->saveAll(array_values($productsToSave));
        }

        foreach ($eventsToDispatch as $event) {
            $this->events->dispatch($event);
        }
    }
}
