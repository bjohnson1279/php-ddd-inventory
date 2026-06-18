<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ReceiveInTransit
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface $events
    ) {}

    public function execute(SKU $sku, Quantity $quantity, LocationId $locationId): void
    {
        $product = $this->productRepository->findBySku($sku);
        if (!$product) {
            throw new Exception("Product not found with SKU: " . $sku->getValue());
        }
        $product->receiveInTransitAt($locationId, $quantity);
        $this->productRepository->save($product);

        foreach ($product->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
