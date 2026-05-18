<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class GetStockLevel
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(string $skuValue, ?string $locationValue = null): int
    {
        $sku = new SKU($skuValue);
        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $skuValue);
        }

        if ($locationValue) {
            return $product->getStockAt(new LocationId($locationValue))->getStockQuantity()->getValue();
        }

        return $product->getTotalStockQuantity()->getValue();
    }
}
