<?php

namespace Tests\Unit\Domain\Accounting\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\Services\CostLayerService;

class AccountingJournalServiceTest extends TestCase
{
    private $repo;
    private $costLayerService;
    private $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(JournalRepositoryInterface::class);
        $this->costLayerService = $this->createMock(CostLayerService::class);
        $this->service = new AccountingJournalService($this->repo, $this->costLayerService);
    }

    public function testOnStockReceivedCreatesEntryForAccrual(): void
    {
        $this->repo->expects($this->once())->method('save')
            ->with($this->isInstanceOf(JournalEntry::class));

        $entry = $this->service->onStockReceived(
            't1',
            new \DateTimeImmutable(),
            'PO-100',
            'Acme Corp',
            50000,
            AccountingMethod::Accrual
        );

        $this->assertNotNull($entry);
        $this->assertEquals(AccountingMethod::Accrual, $entry->method);
        $this->assertCount(2, $entry->lines());
        
        $lines = $entry->lines();
        $this->assertEquals(AccountCode::inventory()->code, $lines[0]->account->code);
        $this->assertEquals(DebitCredit::Debit, $lines[0]->type);
        $this->assertEquals(50000, $lines[0]->amountCents);
    }

    public function testOnStockReceivedReturnsNullForCash(): void
    {
        $this->repo->expects($this->never())->method('save');

        $entry = $this->service->onStockReceived(
            't1',
            new \DateTimeImmutable(),
            'PO-100',
            'Acme Corp',
            50000,
            AccountingMethod::Cash
        );

        $this->assertNull($entry);
    }

    public function testOnSupplierPaidCreatesCorrectEntryForCash(): void
    {
        $this->repo->expects($this->once())->method('save');

        $entry = $this->service->onSupplierPaid(
            't1',
            new \DateTimeImmutable(),
            'PO-100',
            'Acme Corp',
            50000,
            AccountingMethod::Cash
        );

        $this->assertNotNull($entry);
        $this->assertEquals(AccountingMethod::Cash, $entry->method);
        
        $lines = $entry->lines();
        $this->assertEquals(AccountCode::inventoryExpense()->code, $lines[0]->account->code);
    }

    public function testOnSupplierPaidCreatesCorrectEntryForAccrual(): void
    {
        $this->repo->expects($this->once())->method('save');

        $entry = $this->service->onSupplierPaid(
            't1',
            new \DateTimeImmutable(),
            'PO-100',
            'Acme Corp',
            50000,
            AccountingMethod::Accrual
        );

        $this->assertNotNull($entry);
        $this->assertEquals(AccountingMethod::Accrual, $entry->method);
        
        $lines = $entry->lines();
        $this->assertEquals(AccountCode::accountsPayable()->code, $lines[0]->account->code);
        $this->assertEquals(DebitCredit::Debit, $lines[0]->type);
    }

    public function testOnStockSoldAccrualCreatesRevenueAndCogs(): void
    {
        $this->costLayerService->method('consumeFifoLayers')
            ->willReturn(new \InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown(1, 400)); // $4.00 cost

        $this->repo->expects($this->once())->method('save');

        $entry = $this->service->onStockSold(
            't1', 'v1', 1, 1000, true, 'S-1', new \DateTimeImmutable(),
            AccountingMethod::Accrual, \InventoryApp\Domain\Accounting\Enums\CostingMethod::FIFO
        );

        $this->assertNotNull($entry);
        $this->assertCount(4, $entry->lines()); // DR Cash, CR Revenue, DR COGS, CR Inventory
        
        $lines = $entry->lines();
        $this->assertEquals(AccountCode::cash()->code, $lines[0]->account->code);
        $this->assertEquals(1000, $lines[0]->amountCents);
        
        $this->assertEquals(AccountCode::costOfGoodsSold()->code, $lines[2]->account->code);
        $this->assertEquals(400, $lines[2]->amountCents);
    }

    public function testOnStockSoldCashOnlyCreatesRevenueWhenPaid(): void
    {
        $this->repo->expects($this->once())->method('save');

        $entry = $this->service->onStockSold(
            't1', 'v1', 1, 1000, true, 'S-1', new \DateTimeImmutable(),
            AccountingMethod::Cash, \InventoryApp\Domain\Accounting\Enums\CostingMethod::FIFO
        );

        $this->assertNotNull($entry);
        $this->assertCount(2, $entry->lines()); // DR Cash, CR Revenue
    }

    public function testOnStockSoldLifoCreatesEntry(): void
    {
        $this->costLayerService->expects($this->once())->method('consumeLifoLayers')
            ->with('v1', 2)
            ->willReturn(new \InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown(2, 900));

        $this->repo->expects($this->once())->method('save');

        $entry = $this->service->onStockSold(
            't1', 'v1', 2, 2000, true, 'S-2', new \DateTimeImmutable(),
            AccountingMethod::Accrual, \InventoryApp\Domain\Accounting\Enums\CostingMethod::LIFO
        );

        $this->assertNotNull($entry);
        $this->assertCount(4, $entry->lines());
        $lines = $entry->lines();
        $this->assertEquals(AccountCode::costOfGoodsSold()->code, $lines[2]->account->code);
        $this->assertEquals(900, $lines[2]->amountCents);
    }

    public function testOnStockSoldSpecificIdentificationCreatesEntry(): void
    {
        $this->costLayerService->expects($this->once())->method('consumeSpecificLayers')
            ->with('v1', ['SN-100', 'SN-200'])
            ->willReturn(new \InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown(2, 3500));

        $this->repo->expects($this->once())->method('save');

        $entry = $this->service->onStockSold(
            't1', 'v1', 2, 5000, true, 'S-3', new \DateTimeImmutable(),
            AccountingMethod::Accrual, \InventoryApp\Domain\Accounting\Enums\CostingMethod::SpecificIdentification,
            ['SN-100', 'SN-200']
        );

        $this->assertNotNull($entry);
        $this->assertCount(4, $entry->lines());
        $lines = $entry->lines();
        $this->assertEquals(AccountCode::costOfGoodsSold()->code, $lines[2]->account->code);
        $this->assertEquals(3500, $lines[2]->amountCents);
    }

    public function testOnStockSoldSpecificIdentificationThrowsWhenNoSerials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requires serial numbers/');

        $this->service->onStockSold(
            't1', 'v1', 2, 5000, true, 'S-3', new \DateTimeImmutable(),
            AccountingMethod::Accrual, \InventoryApp\Domain\Accounting\Enums\CostingMethod::SpecificIdentification,
            null
        );
    }

    public function testOnStockSoldSpecificIdentificationThrowsWhenQuantityMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must match the quantity sold/');

        $this->service->onStockSold(
            't1', 'v1', 2, 5000, true, 'S-3', new \DateTimeImmutable(),
            AccountingMethod::Accrual, \InventoryApp\Domain\Accounting\Enums\CostingMethod::SpecificIdentification,
            ['SN-100']
        );
    }
}
