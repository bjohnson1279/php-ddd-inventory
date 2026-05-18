<?php

namespace InventoryApp\Domain\Accounting\Services;

use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;

class AccountingJournalService
{
    public function __construct(private readonly $journalRepo, private readonly $costLayerService) {}

    private function createEntry(string $tenantId, \DateTimeImmutable $date, string $description, ?string $referenceId, AccountingMethod $method, array $lines): JournalEntry
    {
        $entry = new JournalEntry(bin2hex(random_bytes(8)), $tenantId, $date, $description, $referenceId, $method);
        foreach ($lines as [$account, $amount, $type, $memo]) {
            $entry->addLine($account, $amount, $type, $memo);
        }
        $entry->assertBalanced();
        $this->journalRepo->save($entry);
        return $entry;
    }
}
