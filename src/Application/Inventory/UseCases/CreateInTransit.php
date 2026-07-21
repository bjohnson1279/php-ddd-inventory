<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class CreateInTransit
{
    public function __construct(private readonly ProductRepositoryInterface $productRepository) {}

    public function execute(SKU $sku, Quantity $quantity, LocationId $locationId): void
    {
        $product = $this->productRepository->findBySku($sku);
        if (!$product) {
            throw new Exception("Product not found with SKU: " . $sku->getValue());
        }
        $product->createInTransitAt($locationId, $quantity);
        $this->productRepository->save($product);
    }
}
