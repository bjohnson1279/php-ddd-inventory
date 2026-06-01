<?php

namespace Tests\Unit\Application\Accounting\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Accounting\Listeners\SyncJournalToXero;
use InventoryApp\Infrastructure\Integration\Xero\XeroJournalSync;
use InventoryApp\Infrastructure\Integration\Xero\XeroMappingRepository;
use InventoryApp\Domain\Accounting\Events\JournalEntryRecorded;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;
use DateTimeImmutable;

class SyncJournalToXeroTest extends TestCase
{
    private $sync;
    private $mappings;
    private $listener;

    protected function setUp(): void
    {
        $this->sync = $this->createMock(XeroJournalSync::class);
        $this->mappings = $this->createMock(XeroMappingRepository::class);
        $this->listener = new SyncJournalToXero($this->sync, $this->mappings);
    }

    public function testHandleSyncsJournalToXero(): void
    {
        $entry = new JournalEntry('local-entry-id', 'test-tenant', new DateTimeImmutable(), 'Test sale to Xero', 'ref-1', AccountingMethod::Accrual);
        $entry->addLine(AccountCode::cash(), 100, DebitCredit::Debit);
        $entry->addLine(AccountCode::salesRevenue(), 100, DebitCredit::Credit);

        $event = new JournalEntryRecorded($entry);

        // Expect finding mapping to return null
        $this->mappings->method('findXeroJournalId')->with('local-entry-id')->willReturn(null);

        // Expect createManualJournal to be called with exact data and return Xero ID
        $this->sync->expects($this->once())
            ->method('createManualJournal')
            ->with(
                'Test sale to Xero',
                'ref-1',
                $this->callback(function ($lines) {
                    return count($lines) === 2 && 
                           $lines[0]['account'] === '1000' && 
                           $lines[1]['account'] === '4000';
                })
            )
            ->willReturn('xero-journal-123');

        // Expect saveMapping to be called with local ID and Xero ID
        $this->mappings->expects($this->once())
            ->method('saveMapping')
            ->with('local-entry-id', 'xero-journal-123');

        $this->listener->handle($event);
    }

    public function testHandleSkipsWhenMappingAlreadyExists(): void
    {
        $entry = new JournalEntry('local-entry-id', 'test-tenant', new DateTimeImmutable(), 'Test sale', 'ref-1', AccountingMethod::Accrual);
        $event = new JournalEntryRecorded($entry);

        // Expect finding mapping to return already existing Xero ID
        $this->mappings->method('findXeroJournalId')->with('local-entry-id')->willReturn('xero-journal-123');

        // Expect Xero client to never be called
        $this->sync->expects($this->never())->method('createManualJournal');

        $this->listener->handle($event);
    }

    public function testHandlePropagatesFailureAndDoesNotSaveMapping(): void
    {
        $entry = new JournalEntry('local-entry-id', 'test-tenant', new DateTimeImmutable(), 'Test sale', 'ref-1', AccountingMethod::Accrual);
        $entry->addLine(AccountCode::cash(), 100, DebitCredit::Debit);
        $entry->addLine(AccountCode::salesRevenue(), 100, DebitCredit::Credit);

        $event = new JournalEntryRecorded($entry);

        $this->mappings->method('findXeroJournalId')->with('local-entry-id')->willReturn(null);

        // Simulate Xero API throws exception
        $this->sync->method('createManualJournal')->willThrowException(new \RuntimeException('Xero Connection Timeout'));

        // Expect saveMapping to never be called
        $this->mappings->expects($this->never())->method('saveMapping');

        $this->expectException(\RuntimeException::class);
        $this->listener->handle($event);
    }
}
