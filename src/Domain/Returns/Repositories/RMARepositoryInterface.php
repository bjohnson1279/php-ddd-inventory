<?php

namespace InventoryApp\Domain\Returns\Repositories;

use InventoryApp\Domain\Returns\Aggregates\RMA;

interface RMARepositoryInterface
{
    public function save(RMA $rma): void;
    public function findById(string $id): ?RMA;
    public function findByNumber(string $rmaNumber): ?RMA;
    /** @return RMA[] */
    public function findAllByTenant(string $tenantId): array;
}
