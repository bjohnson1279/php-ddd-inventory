<?php

namespace Tests\Unit\Domain\Accounting\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;
use DomainException;
use InvalidArgumentException;

class JournalEntryTest extends TestCase
{
    public function testBalancedEntryPassesValidation(): void
    {
        $entry = new JournalEntry('e1', 't1', new \DateTimeImmutable(), 'Test Sale', 'ref-1', AccountingMethod::Accrual);
        
        // Debit AR 100.00
        $entry->addLine(AccountCode::accountsReceivable(), 10000, DebitCredit::Debit, 'Customer owes money');
        // Credit Revenue 100.00
        $entry->addLine(AccountCode::salesRevenue(), 10000, DebitCredit::Credit, 'Sale recorded');

        $entry->assertBalanced();
        $this->assertCount(2, $entry->lines());
    }

    public function testUnbalancedEntryThrowsException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unbalanced journal entry');

        $entry = new JournalEntry('e2', 't1', new \DateTimeImmutable(), 'Bad Entry', null, AccountingMethod::Accrual);
        $entry->addLine(AccountCode::cash(), 1000, DebitCredit::Debit);
        $entry->addLine(AccountCode::salesRevenue(), 900, DebitCredit::Credit);

        $entry->assertBalanced();
    }

    public function testNegativeAmountThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $entry = new JournalEntry('e3', 't1', new \DateTimeImmutable(), 'Invalid Line', null, AccountingMethod::Accrual);
        $entry->addLine(AccountCode::cash(), -500, DebitCredit::Debit);
    }

    public function testZeroAmountThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $entry = new JournalEntry('e4', 't1', new \DateTimeImmutable(), 'Invalid Line', null, AccountingMethod::Accrual);
        $entry->addLine(AccountCode::cash(), 0, DebitCredit::Debit);
    }
}
