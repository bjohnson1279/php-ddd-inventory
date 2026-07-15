<?php

namespace InventoryApp\Domain\Compliance\Repositories;

use InventoryApp\Domain\Compliance\Entities\ComplianceLedgerEntry;

interface ComplianceLedgerRepositoryInterface
{
    public function save(ComplianceLedgerEntry $entry): void;
    
    /**
     * @return ComplianceLedgerEntry[]
     */
    public function findAll(string $tenantId = null): array;
    
    public function getLastEntry(string $tenantId = null): ?ComplianceLedgerEntry;
}
