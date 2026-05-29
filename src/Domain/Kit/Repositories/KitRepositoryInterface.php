<?php

namespace InventoryApp\Domain\Kit\Repositories;

use InventoryApp\Domain\Kit\Aggregates\Kit;

interface KitRepositoryInterface
{
    public function save(Kit $kit): void;
    public function findBySku(string $sku): ?Kit;
    public function findOrFail(string $id): Kit;
}
