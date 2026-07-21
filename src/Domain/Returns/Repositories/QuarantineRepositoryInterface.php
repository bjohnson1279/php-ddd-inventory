<?php

namespace InventoryApp\Domain\Returns\Repositories;

use InventoryApp\Domain\Returns\Aggregates\QuarantineItem;

interface QuarantineRepositoryInterface
{
    public function save(QuarantineItem $item): void;
    public function findById(string $id): ?QuarantineItem;
    /** @return QuarantineItem[] */
    public function findAllByTenant(string $tenantId): array;
}
