<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;

interface ProductRepositoryInterface
{
    public function findById(string $id): ?Product;
    
    public function findBySku(SKU $sku): ?Product;
    
    /**
     * @param SKU[] $skus
     * @return array<string, Product> Array of products indexed by SKU value
     */
    public function findBySkus(array $skus): array;

    public function save(Product $product): void;
    
    /**
     * @param Product[] $products
     */
    public function saveAll(array $products): void;

    public function delete(Product $product): void;
}
