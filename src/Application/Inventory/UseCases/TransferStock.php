<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class TransferStock
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(string $skuValue, string $fromLocation, string $toLocation, int $quantityValue): void
    {
        $sku = new SKU($skuValue);
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
    }
}
