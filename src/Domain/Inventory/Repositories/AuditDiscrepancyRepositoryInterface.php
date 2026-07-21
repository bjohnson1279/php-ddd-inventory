<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\AuditDiscrepancy;

interface AuditDiscrepancyRepositoryInterface
{
    public function save(AuditDiscrepancy $discrepancy): void;
    public function find(string $id): ?AuditDiscrepancy;
    public function findOpen(string $tenantId, string $type, string $referenceId): ?AuditDiscrepancy;
    public function findAll(string $tenantId, ?string $status = null): array;
}
