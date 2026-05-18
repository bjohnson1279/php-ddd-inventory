<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class RegisterProduct
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(string $id, string $skuValue, string $name, string $departmentValue, string $locationValue, int $initialQuantity): void
    {
        $sku = new SKU($skuValue);

        if ($this->productRepository->findBySku($sku)) {
            throw new Exception("Product already exists with SKU: " . $skuValue);
        }

        $department = new Department($departmentValue);
        $quantity = new Quantity($initialQuantity);
        $locationId = new LocationId($locationValue);

        $product = Product::create($id, $sku, $name, $department, $locationId, $quantity);
        
        $this->productRepository->save($product);
    }
}
