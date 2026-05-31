<?php

namespace Tests\Unit\Application\Accounting\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Accounting\Listeners\SyncJournalToQuickBooks;
use InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksJournalSync;
use InventoryApp\Infrastructure\Integration\QuickBooks\QuickBooksMappingRepository;
use InventoryApp\Domain\Accounting\Events\JournalEntryRecorded;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;
use DateTimeImmutable;

class SyncJournalToQuickBooksTest extends TestCase
{
    private $sync;
    private $mappings;
    private $listener;

    protected function setUp(): void
    {
        $this->sync = $this->createMock(QuickBooksJournalSync::class);
        $this->mappings = $this->createMock(QuickBooksMappingRepository::class);
        $this->listener = new SyncJournalToQuickBooks($this->sync, $this->mappings);
    }

    public function testHandleSyncsJournalToQuickBooks(): void
    {
        $entry = new JournalEntry('local-entry-id', 'test-tenant', new DateTimeImmutable(), 'Test sale', 'ref-1', AccountingMethod::Accrual);
        $entry->addLine(AccountCode::cash(), 100, DebitCredit::Debit);
        $entry->addLine(AccountCode::salesRevenue(), 100, DebitCredit::Credit);

        $event = new JournalEntryRecorded($entry);

        // Expect finding mapping to return null
        $this->mappings->method('findQuickBooksJournalId')->with('local-entry-id')->willReturn(null);

        // Expect createJournalEntry to be called with exact data and return QBO ID
        $this->sync->expects($this->once())
            ->method('createJournalEntry')
            ->with(
                'Test sale',
                'ref-1',
                $this->callback(function ($lines) {
                    return count($lines) === 2 && 
                           $lines[0]['account'] === '1000' && 
                           $lines[1]['account'] === '4000';
                })
            )
            ->willReturn('qbo-journal-123');

        // Expect saveMapping to be called with local ID and QBO ID
        $this->mappings->expects($this->once())
            ->method('saveMapping')
            ->with('local-entry-id', 'qbo-journal-123');

        $this->listener->handle($event);
    }

    public function testHandleSkipsWhenMappingAlreadyExists(): void
    {
        $entry = new JournalEntry('local-entry-id', 'test-tenant', new DateTimeImmutable(), 'Test sale', 'ref-1', AccountingMethod::Accrual);
        $event = new JournalEntryRecorded($entry);

        // Expect finding mapping to return already existing QBO ID
        $this->mappings->method('findQuickBooksJournalId')->with('local-entry-id')->willReturn('qbo-journal-123');

        // Expect QBO client to never be called
        $this->sync->expects($this->never())->method('createJournalEntry');

        $this->listener->handle($event);
    }

    public function testHandlePropagatesFailureAndDoesNotSaveMapping(): void
    {
        $entry = new JournalEntry('local-entry-id', 'test-tenant', new DateTimeImmutable(), 'Test sale', 'ref-1', AccountingMethod::Accrual);
        $entry->addLine(AccountCode::cash(), 100, DebitCredit::Debit);
        $entry->addLine(AccountCode::salesRevenue(), 100, DebitCredit::Credit);

        $event = new JournalEntryRecorded($entry);

        $this->mappings->method('findQuickBooksJournalId')->with('local-entry-id')->willReturn(null);

        // Simulate QBO API throws exception
        $this->sync->method('createJournalEntry')->willThrowException(new \RuntimeException('QBO Connection Timeout'));

        // Expect saveMapping to never be called
        $this->mappings->expects($this->never())->method('saveMapping');

        $this->expectException(\RuntimeException::class);
        $this->listener->handle($event);
    }
}
