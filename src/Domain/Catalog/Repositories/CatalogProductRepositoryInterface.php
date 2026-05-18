<?php

namespace InventoryApp\Domain\Catalog\Repositories;

use InventoryApp\Domain\Catalog\Entities\Product;

interface CatalogProductRepositoryInterface
{
    public function findById(string $id): ?Product;
    public function save(Product $product): void;
    public function delete(Product $product): void;
}
