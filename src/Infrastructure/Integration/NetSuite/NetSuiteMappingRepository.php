<?php

namespace InventoryApp\Infrastructure\Integration\NetSuite;

use Illuminate\Database\Capsule\Manager as DB;

class NetSuiteMappingRepository
{
    private array $mappingCache = [];

    /**
     * Resolve a local journal_entry_id to NetSuite journal entry ID.
     */
    public function findNetSuiteJournalId(string $journalEntryId): ?string
    {
        if (!array_key_exists($journalEntryId, $this->mappingCache)) {
            $row = DB::table('netsuite_journal_mappings')
                ->where('journal_entry_id', $journalEntryId)
                ->first(['netsuite_journal_id']);

            $this->mappingCache[$journalEntryId] = $row?->netsuite_journal_id;
        }

        return $this->mappingCache[$journalEntryId] ?: null;
    }

    /**
     * Save the mapping between our local journal_entry_id and NetSuite's netsuite_journal_id.
     */
    public function saveMapping(string $journalEntryId, string $netsuiteJournalId): void
    {
        DB::table('netsuite_journal_mappings')->updateOrInsert(
            ['journal_entry_id' => $journalEntryId],
            [
                'id'                  => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'netsuite_journal_id' => $netsuiteJournalId
            ]
        );

        unset($this->mappingCache[$journalEntryId]);
    }
}
