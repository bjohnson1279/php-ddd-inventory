<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class TransferStock
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
    ) {}

    public function execute(string $skuValue, string $fromLocation, string $toLocation, int $quantityValue): void
    {
        $sku     = new SKU($skuValue);
        $product = $this->productRepository->findBySku($sku);
        
        if (!$product) {
            throw new Exception("Product not found with SKU: " . $skuValue);
        }

        $product->transferStock(
            new LocationId($fromLocation),
            new LocationId($toLocation),
            new Quantity($quantityValue)
        );

        $this->productRepository->save($product);

        foreach ($product->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
