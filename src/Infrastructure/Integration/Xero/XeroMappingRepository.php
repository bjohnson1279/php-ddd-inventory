<?php

namespace InventoryApp\Infrastructure\Integration\Xero;

use Illuminate\Database\Capsule\Manager as DB;

class XeroMappingRepository
{
    private array $mappingCache = [];

    /**
     * Resolve a local journal_entry_id to Xero manual journal ID.
     */
    public function findXeroJournalId(string $journalEntryId): ?string
    {
        if (!array_key_exists($journalEntryId, $this->mappingCache)) {
            $row = DB::table('xero_journal_mappings')
                ->where('journal_entry_id', $journalEntryId)
                ->first(['xero_journal_id']);

            $this->mappingCache[$journalEntryId] = $row?->xero_journal_id;
        }

        return $this->mappingCache[$journalEntryId] ?: null;
    }

    /**
     * Save the mapping between our local journal_entry_id and Xero's xero_journal_id.
     */
    public function saveMapping(string $journalEntryId, string $xeroJournalId): void
    {
        DB::table('xero_journal_mappings')->updateOrInsert(
            ['journal_entry_id' => $journalEntryId],
            [
                'id'              => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'xero_journal_id' => $xeroJournalId
            ]
        );

        unset($this->mappingCache[$journalEntryId]);
    }
}
