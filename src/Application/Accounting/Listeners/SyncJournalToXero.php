<?php

namespace InventoryApp\Application\Accounting\Listeners;

use InventoryApp\Domain\Accounting\Events\JournalEntryRecorded;
use InventoryApp\Domain\Shared\Events\QueuedListenerInterface;
use InventoryApp\Infrastructure\Integration\Xero\XeroJournalSync;
use InventoryApp\Infrastructure\Integration\Xero\XeroMappingRepository;

class SyncJournalToXero implements QueuedListenerInterface
{
    private XeroJournalSync $sync;
    private XeroMappingRepository $mappings;

    public function __construct(
        XeroJournalSync $sync,
        XeroMappingRepository $mappings
    ) {
        $this->sync = $sync;
        $this->mappings = $mappings;
    }

    public function handle(JournalEntryRecorded $event): void
    {
        $entryId = $event->getEntryId();

        // 1. Check if mapping already exists
        if ($this->mappings->findXeroJournalId($entryId) !== null) {
            return; // Already mapped/synchronized, skip
        }

        try {
            // 2. Push journal entry to Xero
            $xeroJournalId = $this->sync->createManualJournal(
                $event->getDescription(),
                $event->getReferenceId(),
                $event->getLines()
            );

            // 3. Save the mapping
            $this->mappings->saveMapping($entryId, $xeroJournalId);

            error_log("Successfully synchronized local Journal Entry {$entryId} outbound to Xero Manual Journal ID {$xeroJournalId}");
        } catch (\Throwable $e) {
            error_log("Xero outbound journal sync failed for local Journal Entry {$entryId}: " . $e->getMessage());
            // Rethrow so the queue worker retries this task
            throw $e;
        }
    }
}
