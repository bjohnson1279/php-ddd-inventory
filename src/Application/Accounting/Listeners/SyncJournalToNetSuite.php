<?php

namespace InventoryApp\Application\Accounting\Listeners;

use InventoryApp\Domain\Accounting\Events\JournalEntryRecorded;
use InventoryApp\Domain\Shared\Events\QueuedListenerInterface;
use InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteJournalSync;
use InventoryApp\Infrastructure\Integration\NetSuite\NetSuiteMappingRepository;

class SyncJournalToNetSuite implements QueuedListenerInterface
{
    private NetSuiteJournalSync $sync;
    private NetSuiteMappingRepository $mappings;

    public function __construct(
        NetSuiteJournalSync $sync,
        NetSuiteMappingRepository $mappings
    ) {
        $this->sync = $sync;
        $this->mappings = $mappings;
    }

    public function handle(JournalEntryRecorded $event): void
    {
        $entryId = $event->getEntryId();

        // 1. Check if mapping already exists
        if ($this->mappings->findNetSuiteJournalId($entryId) !== null) {
            return; // Already mapped/synchronized, skip
        }

        try {
            // 2. Push journal entry to NetSuite
            $nsJournalId = $this->sync->createJournalEntry(
                $event->getDescription(),
                $event->getReferenceId(),
                $event->getLines()
            );

            // 3. Save the mapping
            $this->mappings->saveMapping($entryId, $nsJournalId);

            error_log("Successfully synchronized local Journal Entry {$entryId} outbound to NetSuite Journal ID {$nsJournalId}");
        } catch (\Throwable $e) {
            error_log("NetSuite outbound journal sync failed for local Journal Entry {$entryId}: " . $e->getMessage());
            // Rethrow so the queue worker retries this task
            throw $e;
        }
    }
}
