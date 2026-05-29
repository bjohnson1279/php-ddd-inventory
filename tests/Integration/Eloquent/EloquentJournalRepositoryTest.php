<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentJournalRepository;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentJournalRepositoryTest extends TestCase
{
    private EloquentJournalRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new EloquentJournalRepository();
    }

    public function test_save_and_retrieve_all_journal_entries(): void
    {
        $id = uuidv4();
        $tenantId = 'test-tenant';
        $date = new \DateTimeImmutable('2026-05-29');
        $description = 'Test opening balance entry';
        $referenceId = 'REF-12345';
        $method = AccountingMethod::Accrual;

        $entry = new JournalEntry($id, $tenantId, $date, $description, $referenceId, $method);

        // Add a debit and matching credit line to balance the entry
        $entry->addLine(AccountCode::inventory(), 10000, DebitCredit::Debit, 'Debit inventory');
        $entry->addLine(AccountCode::accountsPayable(), 10000, DebitCredit::Credit, 'Credit AP');
        $entry->assertBalanced();

        $this->repo->save($entry);

        $allEntries = $this->repo->all();
        $this->assertCount(1, $allEntries);

        $loaded = $allEntries[0];
        $this->assertEquals($id, $loaded['id']);
        $this->assertEquals($tenantId, $loaded['tenantId']);
        $this->assertEquals('2026-05-29', $loaded['date']);
        $this->assertEquals($description, $loaded['description']);
        $this->assertEquals($referenceId, $loaded['referenceId']);
        $this->assertEquals($method->value, $loaded['method']);

        $lines = $loaded['lines'];
        $this->assertCount(2, $lines);

        $this->assertEquals(AccountCode::inventory()->code, $lines[0]['account']);
        $this->assertEquals(10000, $lines[0]['amount']);
        $this->assertEquals(DebitCredit::Debit->value, $lines[0]['type']);
        $this->assertEquals('Debit inventory', $lines[0]['memo']);

        $this->assertEquals(AccountCode::accountsPayable()->code, $lines[1]['account']);
        $this->assertEquals(10000, $lines[1]['amount']);
        $this->assertEquals(DebitCredit::Credit->value, $lines[1]['type']);
        $this->assertEquals('Credit AP', $lines[1]['memo']);
    }
}
