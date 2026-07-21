<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\LedgerEntry;

interface LedgerRepositoryInterface
{
    public function append(LedgerEntry $entry): void;

    /**
     * @param LedgerEntry[] $entries
     */
    public function appendAll(array $entries): void;

    public function currentQuantity(string $variantId): int;

     * @param string[] $variantIds
     * @return array<string, int> Array of current quantities indexed by variantId
    public function currentQuantities(array $variantIds): array;

    /** @return LedgerEntry[] */
    public function entriesFor(string $variantId, ?string $locationId = null): array;

     * @return LedgerEntry[]
    public function entriesForSkusAndLocation(array $variantIds, string $locationId): array;

     * Returns true if any ledger entries exist for this variant at this location.
    public function hasAnyEntries(string $variantId, string $locationId): bool;

    public function findRecallEntries(string $lotNumber): array;
}



{






}
