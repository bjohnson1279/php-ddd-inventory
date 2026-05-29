<?php

namespace InventoryApp\Domain\Uom\Repositories;

use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;

interface ProductUomConfigurationRepositoryInterface
{
    public function save(ProductUomConfiguration $config): void;
    public function findByVariant(string $variantId): ?ProductUomConfiguration;
    public function findOrFail(string $id): ProductUomConfiguration;
}
