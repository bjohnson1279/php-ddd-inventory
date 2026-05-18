<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;

interface ProductRepositoryInterface
{
    public function findById(string $id): ?Product;
    
    public function findBySku(SKU $sku): ?Product;
    
    public function save(Product $product): void;
    
    public function delete(Product $product): void;
}
