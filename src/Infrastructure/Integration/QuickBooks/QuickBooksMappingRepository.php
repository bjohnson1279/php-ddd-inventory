<?php

namespace InventoryApp\Infrastructure\Integration\QuickBooks;

use Illuminate\Database\Capsule\Manager as DB;

class QuickBooksMappingRepository
{
    private array $mappingCache = [];

    /**
     * Resolve a local journal_entry_id to QuickBooks journal entry ID.
     */
    public function findQuickBooksJournalId(string $journalEntryId): ?string
    {
        if (!array_key_exists($journalEntryId, $this->mappingCache)) {
            $row = DB::table('quickbooks_journal_mappings')
                ->where('journal_entry_id', $journalEntryId)
                ->first(['quickbooks_journal_id']);

            $this->mappingCache[$journalEntryId] = $row?->quickbooks_journal_id;
        }

        return $this->mappingCache[$journalEntryId] ?: null;
    }

    /**
     * Save the mapping between our local journal_entry_id and QuickBooks' quickbooks_journal_id.
     */
    public function saveMapping(string $journalEntryId, string $quickbooksJournalId): void
    {
        DB::table('quickbooks_journal_mappings')->updateOrInsert(
            ['journal_entry_id'     => $journalEntryId],
            [
                'id'                    => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'quickbooks_journal_id' => $quickbooksJournalId
            ]
        );

        unset($this->mappingCache[$journalEntryId]);
    }
}
