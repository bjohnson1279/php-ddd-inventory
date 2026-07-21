<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ProcessSale
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
    ) {}

    public function execute(string $skuValue, string $locationValue, int $quantityValue, ?string $orderId = null): void
    {
        $sku        = new SKU($skuValue);
        $quantity   = new Quantity($quantityValue);
        $locationId = new LocationId($locationValue);

        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $skuValue);
        }

        $product->processSaleAt($locationId, $quantity, $orderId);
        $this->productRepository->save($product);

        foreach ($product->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }

    public function executeBulk(array $sales, ?string $orderId = null): void
    {
        $skus = [];
        foreach ($sales as $sale) {
            $skus[] = new SKU($sale['sku']);
        }

        $products = $this->productRepository->findBySkus($skus);

        foreach ($sales as $sale) {
            $skuValue = $sale['sku'];
            if (!isset($products[$skuValue])) {
                throw new Exception("Product not found with SKU: " . $skuValue);
            }

            $product = $products[$skuValue];

            $quantity   = new Quantity((int)$sale['quantity']);
            $locationId = new LocationId($sale['location']);

            $product->processSaleAt($locationId, $quantity, $orderId);
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
