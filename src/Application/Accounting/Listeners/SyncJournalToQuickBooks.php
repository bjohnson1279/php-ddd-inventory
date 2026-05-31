<?php

namespace InventoryApp\Application\Accounting\Listeners;

use InventoryApp\Domain\Accounting\Events\JournalEntryRecorded;
use InventoryApp\Domain\Shared\Events\QueuedListenerInterface;
use InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksJournalSync;
use InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksMappingRepository;

class SyncJournalToQuickBooks implements QueuedListenerInterface
{
    private QuickBooksJournalSync $sync;
    private QuickBooksMappingRepository $mappings;

    public function __construct(
        QuickBooksJournalSync $sync,
        QuickBooksMappingRepository $mappings
    ) {
        $this->sync = $sync;
        $this->mappings = $mappings;
    }

    public function handle(JournalEntryRecorded $event): void
    {
        $entryId = $event->getEntryId();

        // 1. Check if mapping already exists
        if ($this->mappings->findQuickBooksJournalId($entryId) !== null) {
            return; // Already mapped/synchronized, skip
        }

        try {
            // 2. Push journal entry to QuickBooks
            $qboJournalId = $this->sync->createJournalEntry(
                $event->getDescription(),
                $event->getReferenceId(),
                $event->getLines()
            );

            // 3. Save the mapping
            $this->mappings->saveMapping($entryId, $qboJournalId);

            error_log("Successfully synchronized local Journal Entry {$entryId} outbound to QuickBooks Journal ID {$qboJournalId}");
        } catch (\Throwable $e) {
            error_log("QuickBooks outbound journal sync failed for local Journal Entry {$entryId}: " . $e->getMessage());
            // Rethrow so the queue worker retries this task
            throw $e;
        }
    }
}
