<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ProcessReturnBatch
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
    ) {}

    /**
     * @param array<int, array{sku: string, location: string, quantity: int, condition: string}> $items
     * @param string|null $orderId
     */
    public function execute(array $items, ?string $orderId = null): void
    {
        $skus = [];
        $validItems = [];

        foreach ($items as $item) {
            $skuValue = $item['sku'] ?? '';
            $qty = (int)($item['quantity'] ?? 0);

            if (empty($skuValue) || $qty <= 0) {
                continue;
            }

            $skus[] = new SKU($skuValue);
            $validItems[] = [
                'sku' => $skuValue,
                'location' => $item['location'],
                'qty' => $qty,
                'condition' => $item['condition'] ?? Condition::NEW
            ];
        }

        if (empty($validItems)) {
            return;
        }

        $products = $this->productRepository->findBySkus($skus);
        $modifiedProducts = [];

        foreach ($validItems as $item) {
            $skuValue = $item['sku'];
            if (!isset($products[$skuValue])) {
                throw new Exception("Product not found with SKU: " . $skuValue);
            }

            $product = $products[$skuValue];
            $locationId = new LocationId($item['location']);
            $quantity = new Quantity($item['qty']);
            $condition = new Condition(strtolower($item['condition']));

            $product->processReturnAt($locationId, $quantity, $condition, $orderId);
            $modifiedProducts[$product->getId()] = $product;
        }

        if (!empty($modifiedProducts)) {
            $this->productRepository->saveAll(array_values($modifiedProducts));

            \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::beginBatch(array_values($modifiedProducts));
            try {
                foreach ($modifiedProducts as $product) {
                    foreach ($product->releaseEvents() as $event) {
                        $this->events->dispatch($event);
                    }
                }
            } finally {
                \InventoryApp\Application\Inventory\Listeners\SyncStockToShopify::endBatch();
            }
        }
    }
}
