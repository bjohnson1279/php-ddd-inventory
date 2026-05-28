<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ReceiveStock
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
    ) {}

    public function execute(string $skuValue, string $locationValue, int $quantityValue, ?string $reference = null): void
    {
        $sku        = new SKU($skuValue);
        $quantity   = new Quantity($quantityValue);
        $locationId = new LocationId($locationValue);

        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $skuValue);
        }

        $product->receiveStockAt($locationId, $quantity, $reference);
        $this->productRepository->save($product);

        foreach ($product->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
