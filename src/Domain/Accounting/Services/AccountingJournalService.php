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
        \InventoryApp\Domain\Accounting\Enums\CostingMethod $costingMethod,
        ?array $serialNumbers = null
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
        
        switch ($costingMethod) {
            case \InventoryApp\Domain\Accounting\Enums\CostingMethod::FIFO:
                $cogsBreakdown = $this->costLayerService->consumeFifoLayers($variantId, $quantity);
                break;
            case \InventoryApp\Domain\Accounting\Enums\CostingMethod::LIFO:
                $cogsBreakdown = $this->costLayerService->consumeLifoLayers($variantId, $quantity);
                break;
            case \InventoryApp\Domain\Accounting\Enums\CostingMethod::WeightedAverageCost:
                $cogsBreakdown = $this->costLayerService->calculateWeightedAverageCost($variantId, $quantity);
                break;
            case \InventoryApp\Domain\Accounting\Enums\CostingMethod::SpecificIdentification:
                if (empty($serialNumbers)) {
                    throw new \InvalidArgumentException("Specific Identification costing method requires serial numbers.");
                }
                if (count($serialNumbers) !== $quantity) {
                    throw new \InvalidArgumentException("The number of serial numbers must match the quantity sold.");
                }
                $cogsBreakdown = $this->costLayerService->consumeSpecificLayers($variantId, $serialNumbers);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported costing method.");
        }

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

    public function onStockReturned(
        string $tenantId,
        string $variantId,
        int $totalCostCents,
        string $referenceId,
        \DateTimeImmutable $date
    ): JournalEntry {
        return $this->createEntry(
            $tenantId,
            $date,
            "Inventory return receipt — variant {$variantId} — reference {$referenceId}",
            $referenceId,
            AccountingMethod::Accrual,
            [
                [AccountCode::inventory(), $totalCostCents, DebitCredit::Debit, "Returned stock"],
                [AccountCode::costOfGoodsSold(), $totalCostCents, DebitCredit::Credit, "COGS reversal"],
            ]
        );
    }

    public function onInventoryWriteOff(
        string $tenantId,
        string $referenceId,
        int $totalCostCents,
        \DateTimeImmutable $date
    ): JournalEntry {
        return $this->createEntry(
            $tenantId,
            $date,
            "Inventory Write-Off — Ref {$referenceId}",
            $referenceId,
            AccountingMethod::Accrual,
            [
                [AccountCode::inventoryWriteOffExpense(), $totalCostCents, DebitCredit::Debit, "Inventory write-off"],
                [AccountCode::inventory(), $totalCostCents, DebitCredit::Credit, "Inventory reduction"],
            ]
        );
    }

    public function onReturnToVendor(
        string $tenantId,
        string $referenceId,
        int $totalCostCents,
        \DateTimeImmutable $date
    ): JournalEntry {
        return $this->createEntry(
            $tenantId,
            $date,
            "Return to Vendor — Ref {$referenceId}",
            $referenceId,
            AccountingMethod::Accrual,
            [
                [AccountCode::accountsPayable(), $totalCostCents, DebitCredit::Debit, "AP cleared — return to vendor"],
                [AccountCode::inventory(), $totalCostCents, DebitCredit::Credit, "Inventory reduction"],
            ]
        );
    }

    public function onKitAssembly(
        string $tenantId,
        \DateTimeImmutable $date,
        string $kitSku,
        int $totalCostCents,
        string $referenceId
    ): JournalEntry {
        return $this->createEntry(
            $tenantId,
            $date,
            "Assemble Kit {$kitSku}",
            $referenceId,
            AccountingMethod::Accrual,
            [
                [AccountCode::inventory(), $totalCostCents, DebitCredit::Debit, "Debit Kit Inventory for {$kitSku} assembly"],
                [AccountCode::fromCode('1210'), $totalCostCents, DebitCredit::Credit, "Credit Component Inventory for {$kitSku} assembly"],
            ]
        );
    }

    public function onKitDisassembly(
        string $tenantId,
        \DateTimeImmutable $date,
        string $kitSku,
        int $totalCostCents,
        string $referenceId
    ): JournalEntry {
        return $this->createEntry(
            $tenantId,
            $date,
            "Disassemble Kit {$kitSku}",
            $referenceId,
            AccountingMethod::Accrual,
            [
                [AccountCode::fromCode('1210'), $totalCostCents, DebitCredit::Debit, "Debit Component Inventory for {$kitSku} disassembly"],
                [AccountCode::inventory(), $totalCostCents, DebitCredit::Credit, "Credit Kit Inventory for {$kitSku} disassembly"],
            ]
        );
    }

    private function createEntry(string $tenantId, \DateTimeImmutable $date, string $description, ?string $referenceId, AccountingMethod $method, array $lines): JournalEntry
    {
        $entry = new JournalEntry(\Ramsey\Uuid\Uuid::uuid4()->toString(), $tenantId, $date, $description, $referenceId, $method);
        foreach ($lines as [$account, $amount, $type, $memo]) {
            $entry->addLine($account, $amount, $type, $memo);
        }
        $entry->assertBalanced();
        $this->journalRepo->save($entry);
        return $entry;
    }
}
