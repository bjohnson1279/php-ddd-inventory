<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\InventoryCount;

interface InventoryCountRepositoryInterface
{
    public function findById(string $id): ?InventoryCount;

    public function save(InventoryCount $inventoryCount): void;
}
