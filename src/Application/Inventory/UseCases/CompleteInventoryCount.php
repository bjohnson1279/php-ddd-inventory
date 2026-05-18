<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use Exception;

class CompleteInventoryCount
{
    private InventoryCountRepositoryInterface $countRepository;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        InventoryCountRepositoryInterface $countRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->countRepository = $countRepository;
        $this->productRepository = $productRepository;
    }

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
        foreach ($inventoryCount->getItems() as $item) {
            $product = $this->productRepository->findBySku($item->getSku());

            if ($product) {
                // We use the new reconcile method added to the Product aggregate
                // Hardcoded to LOC-STOREFRONT until InventoryCount items support locations
                $product->reconcileStockAt(
                    new \InventoryApp\Domain\Inventory\ValueObjects\LocationId('LOC-STOREFRONT'),
                    $item->getCountedQuantity(),
                    'COUNT_' . $inventoryCount->getId()
                );
                $this->productRepository->save($product);
            } else {
                // Depending on business rules, you might throw an exception,
                // log a warning, or automatically create a new Product entity here.
            }
        }
    }
}
