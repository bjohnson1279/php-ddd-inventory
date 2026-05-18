<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class ProcessReturn
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(string $skuValue, string $locationValue, int $quantityValue, string $conditionValue, ?string $orderId = null): void
    {
        $sku = new SKU($skuValue);
        $quantity = new Quantity($quantityValue);
        // Accept condition input case-insensitively (tests may pass UPPERCASE)
        $condition = new Condition(strtolower($conditionValue));
        $locationId = new LocationId($locationValue);

        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $skuValue);
        }

        $product->processReturnAt($locationId, $quantity, $condition, $orderId);
        
        $this->productRepository->save($product);
    }
}
