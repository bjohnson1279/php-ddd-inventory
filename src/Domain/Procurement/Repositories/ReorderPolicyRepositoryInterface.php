<?php

namespace InventoryApp\Domain\Procurement\Repositories;

use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;

interface ReorderPolicyRepositoryInterface
{
    public function findBySkuAndLocation(SKU $sku, string $locationId): ?ReorderPolicy;
    public function findBySkusAndLocation(array $skus, string $locationId): array;

    /**
     * @return ReorderPolicy[]
     */
     public function findAllByLocation(string $locationId): array;

    public function save(ReorderPolicy $policy): void;
    public function findAll(): array;
}
