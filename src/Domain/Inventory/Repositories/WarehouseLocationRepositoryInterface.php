<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\WarehouseLocation;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;

interface WarehouseLocationRepositoryInterface
{
    public function save(WarehouseLocation $location): void;
    public function findById(LocationId $id): ?WarehouseLocation;
    public function delete(LocationId $id): void;
    /**
     * @param LocationId[] $ids
     * @return WarehouseLocation[]
     */
    public function findByIds(array $ids): array;
    /**
     * @return WarehouseLocation[]
     */
    public function findAll(): array;
}
