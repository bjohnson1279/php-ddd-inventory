<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ProcessReturn
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
    ) {}

    public function execute(string $skuValue, string $locationValue, int $quantityValue, string $conditionValue, ?string $orderId = null): void
    {
        $sku        = new SKU($skuValue);
        $quantity   = new Quantity($quantityValue);
        // Accept condition input case-insensitively (tests may pass UPPERCASE)
        $condition  = new Condition(strtolower($conditionValue));
        $locationId = new LocationId($locationValue);

        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $skuValue);
        }

        $product->processReturnAt($locationId, $quantity, $condition, $orderId);
        $this->productRepository->save($product);

        foreach ($product->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }

    public function executeBulk(array $returns, ?string $orderId = null): void
    {
        $skus = [];
        foreach ($returns as $returnItem) {
            $skus[] = new SKU($returnItem['sku']);
        }

        $products = $this->productRepository->findBySkus($skus);

        foreach ($returns as $returnItem) {
            $skuValue = $returnItem['sku'];
            if (!isset($products[$skuValue])) {
                throw new Exception("Product not found with SKU: " . $skuValue);
            }

            $product = $products[$skuValue];

            $quantity   = new Quantity((int)$returnItem['quantity']);
            // Accept condition input case-insensitively
            $condition  = new Condition(strtolower($returnItem['condition']));
            $locationId = new LocationId($returnItem['location']);

            $product->processReturnAt($locationId, $quantity, $condition, $orderId);
        }

        $this->productRepository->saveAll(array_values($products));

        \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::beginBatch(array_values($products));
        try {
            foreach ($products as $product) {
                foreach ($product->releaseEvents() as $event) {
                    $this->events->dispatch($event);
                }
            }
        } finally {
            \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::endBatch();
        }
    }
}
