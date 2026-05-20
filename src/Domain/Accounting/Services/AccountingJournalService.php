<?php

namespace InventoryApp\Domain\Accounting\Services;

use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;

class AccountingJournalService
{
    public function __construct(
        private readonly \InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface $journalRepo,
        private readonly CostLayerService $costLayerService
    ) {}

    public function onStockReceived(
        string $tenantId,
        \DateTimeImmutable $date,
        string $purchaseOrderId,
        string $supplierName,
        int $totalCostCents,
        AccountingMethod $method
    ): ?JournalEntry {
        if ($method === AccountingMethod::Cash) {
            return null;
        }

        return $this->createEntry(
            $tenantId,
            $date,
            "Inventory received — PO {$purchaseOrderId}",
            $purchaseOrderId,
            $method,
            [
                [AccountCode::inventory(), $totalCostCents, DebitCredit::Debit, "Received from {$supplierName}"],
                [AccountCode::accountsPayable(), $totalCostCents, DebitCredit::Credit, "AP — {$supplierName} — PO {$purchaseOrderId}"],
            ]
        );
    }

    public function onSupplierPaid(
        string $tenantId,
        \DateTimeImmutable $date,
        string $purchaseOrderId,
        string $supplierName,
        int $amountCents,
        AccountingMethod $method
    ): JournalEntry {
        if ($method === AccountingMethod::Accrual) {
            return $this->createEntry(
                $tenantId,
                $date,
                "Supplier payment — {$supplierName}",
                $purchaseOrderId,
                $method,
                [
                    [AccountCode::accountsPayable(), $amountCents, DebitCredit::Debit, "AP cleared — {$supplierName}"],
                    [AccountCode::cash(), $amountCents, DebitCredit::Credit, "Payment to {$supplierName}"],
                ]
            );
        }

        return $this->createEntry(
            $tenantId,
            $date,
            "Inventory purchase — {$supplierName}",
            $purchaseOrderId,
            $method,
            [
                [AccountCode::inventoryExpense(), $amountCents, DebitCredit::Debit, "Inventory purchased from {$supplierName}"],
                [AccountCode::cash(), $amountCents, DebitCredit::Credit, "Payment to {$supplierName}"],
            ]
        );
    }

    public function onStockSold(
        string $tenantId,
        string $variantId,
        int $quantity,
        int $salePriceCents,
        bool $paymentReceivedNow,
        string $saleId,
        \DateTimeImmutable $date,
        AccountingMethod $method,
        \InventoryApp\Domain\Accounting\Enums\CostingMethod $costingMethod
    ): ?JournalEntry {
        if ($method === AccountingMethod::Cash) {
            if (!$paymentReceivedNow) return null;
            return $this->createEntry(
                $tenantId,
                $date,
                "Sale — {$saleId}",
                $saleId,
                $method,
                [
                    [AccountCode::cash(), $salePriceCents, DebitCredit::Debit, 'Cash received'],
                    [AccountCode::salesRevenue(), $salePriceCents, DebitCredit::Credit, 'Revenue'],
                ]
            );
        }

        // Accrual Method
        $receivableAccount = $paymentReceivedNow ? AccountCode::cash() : AccountCode::accountsReceivable();
        
        $cogsBreakdown = $costingMethod === \InventoryApp\Domain\Accounting\Enums\CostingMethod::FIFO
            ? $this->costLayerService->consumeFifoLayers($variantId, $quantity)
            : $this->costLayerService->calculateWeightedAverageCost($variantId, $quantity);

        return $this->createEntry(
            $tenantId,
            $date,
            "Sale — {$saleId}",
            $saleId,
            $method,
            [
                [$receivableAccount, $salePriceCents, DebitCredit::Debit, $paymentReceivedNow ? 'Cash' : 'AR'],
                [AccountCode::salesRevenue(), $salePriceCents, DebitCredit::Credit, 'Revenue'],
                [AccountCode::costOfGoodsSold(), $cogsBreakdown->totalCostCents, DebitCredit::Debit, 'COGS'],
                [AccountCode::inventory(), $cogsBreakdown->totalCostCents, DebitCredit::Credit, 'Inventory reduction'],
            ]
        );
    }

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
