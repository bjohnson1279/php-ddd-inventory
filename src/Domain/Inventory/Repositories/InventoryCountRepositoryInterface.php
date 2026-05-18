<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\InventoryCount;

interface InventoryCountRepositoryInterface
{
    public function findById(string $id): ?InventoryCount;
    
    public function save(InventoryCount $inventoryCount): void;
    
    // Optional: could have a method to find the currently active count
    // public function findActiveCount(): ?InventoryCount;
}
