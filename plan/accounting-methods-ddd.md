# Accrual vs Cash Accounting

## The Core Problem

Accounting method determines **when** financial events are recognized — not
whether they happen. The same inventory movement (receive goods, sell goods,
pay supplier) produces different journal entries depending on the method.

For an inventory system, this matters in three places:

| Event | Cash | Accrual |
|---|---|---|
| Goods received from supplier | No entry — defer until payment | DR Inventory / CR Accounts Payable |
| Supplier invoice paid | DR Expense / CR Cash | DR Accounts Payable / CR Cash |
| Goods sold (cash at POS) | DR Cash / CR Revenue | DR Cash / CR Revenue + DR COGS / CR Inventory |
| Goods sold (on account) | No entry — defer until payment | DR AR / CR Revenue + DR COGS / CR Inventory |
| Customer payment received | DR Cash / CR Revenue | DR Cash / CR Accounts Receivable |

**Important practical note:** The IRS (IRC §448) requires businesses with average
annual gross receipts over $29M (2024 threshold) to use accrual accounting. Most
small retailers qualify for cash method, but as your tenants scale, their accountants
will push them toward accrual. Design the model to support both from the start.

---

## Bounded Context Separation

The inventory ledger and the accounting journal are **two separate bounded
contexts** that react to the same domain events:

```
Inventory Event
    │
    ├──► InventoryLedger (tracks units — always the same regardless of method)
    │
    └──► AccountingJournal (tracks money — differs by accounting method)
```

The inventory system raises events. A separate accounting context subscribes to
those events and produces journal entries. The accounting method is a configuration
concern on the tenant, not a parameter passed into inventory operations.

---

## Directory Structure

```
app/
└── Domain/
    └── Accounting/
        ├── Aggregates/
        │   └── JournalEntry.php
        ├── Entities/
        │   ├── JournalLine.php
        │   └── InventoryCostLayer.php
        ├── Enums/
        │   ├── AccountingMethod.php
        │   ├── CostingMethod.php
        │   └── DebitCredit.php
        ├── ValueObjects/
        │   ├── AccountCode.php
        │   ├── CostBreakdown.php
        │   └── MoneyAmount.php
        ├── Services/
        │   ├── AccountingJournalService.php
        │   └── CostLayerService.php
        ├── Repositories/
        │   ├── JournalRepositoryInterface.php
        │   └── CostLayerRepositoryInterface.php
        └── Listeners/
            ├── OnStockReceived.php
            ├── OnStockSold.php
            ├── OnSupplierPaid.php
            └── OnCustomerPaymentReceived.php
```

---

## 1. Configuration Enums

```php
// Domain/Accounting/Enums/AccountingMethod.php

enum AccountingMethod: string
{
    case Cash    = 'cash';
    case Accrual = 'accrual';
}
```

```php
// Domain/Accounting/Enums/CostingMethod.php
//
// How to calculate the cost of goods sold when inventory is sold.
// Only meaningful under accrual accounting — cash method expenses
// inventory when paid, so there are no cost layers to resolve.

enum CostingMethod: string
{
    case FIFO                  = 'fifo';                   // First in, first out
    case WeightedAverageCost   = 'weighted_average_cost';  // Running average
    case SpecificIdentification = 'specific_identification'; // Per serial number
}
```

```php
// Domain/Accounting/Enums/DebitCredit.php

enum DebitCredit: string
{
    case Debit  = 'debit';
    case Credit = 'credit';
}
```

---

## 2. Tenant Accounting Configuration

```php
// Domain/Accounting/ValueObjects/TenantAccountingConfig.php
//
// Per-tenant configuration. Stored on the Tenant aggregate.

final class TenantAccountingConfig
{
    public function __construct(
        public readonly AccountingMethod $accountingMethod,
        public readonly CostingMethod $costingMethod,
        public readonly string $currencyCode,     // 'USD', 'GBP', etc.
        public readonly string $fiscalYearStart,  // 'MM-DD', e.g. '01-01'
    ) {
        if (
            $accountingMethod === AccountingMethod::Cash
            && $costingMethod !== CostingMethod::WeightedAverageCost
        ) {
            // Cash accounting doesn't track cost layers — WAC is the
            // closest approximation when cost reporting is needed
            throw new \InvalidArgumentException(
                'Cash accounting should use WeightedAverageCost as the costing method.'
            );
        }
    }
}
```

---

## 3. Chart of Accounts

```php
// Domain/Accounting/ValueObjects/AccountCode.php
//
// A simple account code with category metadata.
// In production, these are configurable per tenant and map to their
// QuickBooks/Xero chart of accounts.

final class AccountCode
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly AccountCategory $category,
    ) {}

    // --- Standard accounts used by the inventory system ---
    // These are defaults — tenants can remap to their own codes.

    public static function cash(): self
    {
        return new self('1000', 'Cash', AccountCategory::Asset);
    }

    public static function accountsReceivable(): self
    {
        return new self('1100', 'Accounts Receivable', AccountCategory::Asset);
    }

    public static function inventory(): self
    {
        return new self('1200', 'Inventory', AccountCategory::Asset);
    }

    public static function accountsPayable(): self
    {
        return new self('2000', 'Accounts Payable', AccountCategory::Liability);
    }

    public static function salesRevenue(): self
    {
        return new self('4000', 'Sales Revenue', AccountCategory::Revenue);
    }

    public static function costOfGoodsSold(): self
    {
        return new self('5000', 'Cost of Goods Sold', AccountCategory::Expense);
    }

    public static function inventoryExpense(): self
    {
        // Used under cash accounting — purchasing inventory is an immediate expense
        return new self('5100', 'Inventory Purchases', AccountCategory::Expense);
    }
}

enum AccountCategory: string
{
    case Asset     = 'asset';
    case Liability = 'liability';
    case Equity    = 'equity';
    case Revenue   = 'revenue';
    case Expense   = 'expense';
}
```

---

## 4. Journal Entry Aggregate

```php
// Domain/Accounting/Aggregates/JournalEntry.php
//
// A double-entry bookkeeping record. Every entry must balance:
// sum of debits === sum of credits.

class JournalEntry
{
    /** @var JournalLine[] */
    private array $lines = [];

    public function __construct(
        public readonly JournalEntryId $id,
        public readonly TenantId $tenantId,
        public readonly \DateTimeImmutable $date,
        public readonly string $description,
        public readonly ?string $referenceId,       // saleId, poId, etc.
        public readonly AccountingMethod $method,
    ) {}

    public function addLine(
        AccountCode $account,
        int $amountCents,
        DebitCredit $type,
        string $memo = '',
    ): void {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Journal line amount must be positive.');
        }

        $this->lines[] = new JournalLine(
            id: JournalLineId::generate(),
            account: $account,
            amountCents: $amountCents,
            type: $type,
            memo: $memo,
        );
    }

    /**
     * Validate that the entry balances before persisting.
     * A journal entry where debits ≠ credits is always a bug.
     */
    public function assertBalanced(): void
    {
        $totalDebits  = array_sum(array_map(
            fn(JournalLine $l) => $l->type === DebitCredit::Debit ? $l->amountCents : 0,
            $this->lines,
        ));
        $totalCredits = array_sum(array_map(
            fn(JournalLine $l) => $l->type === DebitCredit::Credit ? $l->amountCents : 0,
            $this->lines,
        ));

        if ($totalDebits !== $totalCredits) {
            throw new UnbalancedJournalEntryException(
                "Debits ({$totalDebits}¢) do not equal credits ({$totalCredits}¢) "
                . "in entry {$this->id->value}."
            );
        }
    }

    /** @return JournalLine[] */
    public function lines(): array
    {
        return $this->lines;
    }
}
```

```php
// Domain/Accounting/Entities/JournalLine.php

final class JournalLine
{
    public function __construct(
        public readonly JournalLineId $id,
        public readonly AccountCode $account,
        public readonly int $amountCents,
        public readonly DebitCredit $type,
        public readonly string $memo,
    ) {}
}
```

---

## 5. Cost Layer Tracking (FIFO / WAC)

```php
// Domain/Accounting/Entities/InventoryCostLayer.php
//
// Represents a batch of inventory received at a specific cost.
// Used to calculate COGS under FIFO or specific identification.
// Under weighted average cost, layers are aggregated into a running average.

class InventoryCostLayer
{
    private int $remainingQuantity;

    public function __construct(
        public readonly CostLayerId $id,
        public readonly ProductVariantId $variantId,
        public readonly TenantId $tenantId,
        public readonly int $originalQuantity,
        public readonly int $unitCostCents,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly string $purchaseOrderId,
    ) {
        $this->remainingQuantity = $originalQuantity;
    }

    /**
     * Consume up to $needed units from this layer.
     * Returns how many were actually consumed (may be less than $needed
     * if the layer doesn't have enough).
     */
    public function consume(int $needed): int
    {
        $consumed = min($needed, $this->remainingQuantity);
        $this->remainingQuantity -= $consumed;
        return $consumed;
    }

    public function remainingQuantity(): int
    {
        return $this->remainingQuantity;
    }

    public function remainingCostCents(): int
    {
        return $this->remainingQuantity * $this->unitCostCents;
    }

    public function isExhausted(): bool
    {
        return $this->remainingQuantity === 0;
    }
}
```

```php
// Domain/Accounting/ValueObjects/CostBreakdown.php
//
// The result of a COGS calculation — how much it cost to produce/acquire
// the units being sold.

final class CostBreakdown
{
    public function __construct(
        public readonly int $units,
        public readonly int $totalCostCents,
    ) {}

    public function unitCostCents(): int
    {
        return $this->units > 0
            ? (int) round($this->totalCostCents / $this->units)
            : 0;
    }
}
```

```php
// Domain/Accounting/Services/CostLayerService.php
//
// Calculates and consumes cost layers when inventory is sold.

class CostLayerService
{
    public function __construct(
        private readonly CostLayerRepositoryInterface $layers,
    ) {}

    // -------------------------------------------------------------------------
    // FIFO — consume oldest layers first
    // -------------------------------------------------------------------------

    public function calculateFifoCost(
        ProductVariantId $variantId,
        int $quantity,
    ): CostBreakdown {
        // Ordered by receivedAt ASC — oldest first
        $activeLayers = $this->layers->getActiveLayers($variantId, orderBy: 'received_at ASC');
        return $this->consumeLayers($activeLayers, $quantity);
    }

    public function consumeFifoLayers(ProductVariantId $variantId, int $quantity): CostBreakdown
    {
        $activeLayers = $this->layers->getActiveLayers($variantId, orderBy: 'received_at ASC');
        $breakdown    = $this->consumeLayers($activeLayers, $quantity);

        foreach ($activeLayers as $layer) {
            $this->layers->save($layer); // persist consumed quantities
        }

        return $breakdown;
    }

    // -------------------------------------------------------------------------
    // Weighted Average Cost — average across all active layers
    // -------------------------------------------------------------------------

    public function calculateWeightedAverageCost(
        ProductVariantId $variantId,
        int $quantity,
    ): CostBreakdown {
        $activeLayers = $this->layers->getActiveLayers($variantId);

        $totalUnits = array_sum(
            array_map(fn(InventoryCostLayer $l) => $l->remainingQuantity(), $activeLayers)
        );
        $totalValue = array_sum(
            array_map(fn(InventoryCostLayer $l) => $l->remainingCostCents(), $activeLayers)
        );

        if ($totalUnits === 0) {
            throw new InsufficientInventoryException($variantId, 0, $quantity);
        }

        $avgCostCents = $totalValue / $totalUnits;
        return new CostBreakdown($quantity, (int) round($quantity * $avgCostCents));
    }

    // -------------------------------------------------------------------------
    // Specific Identification — for serialized items
    // Requires the exact serial number to look up its cost layer
    // -------------------------------------------------------------------------

    public function costForSerial(
        ProductVariantId $variantId,
        SerialNumber $serialNumber,
    ): CostBreakdown {
        $layer = $this->layers->findBySerial($variantId, $serialNumber);

        if ($layer === null) {
            throw new \DomainException(
                "No cost layer found for serial {$serialNumber->value}."
            );
        }

        return new CostBreakdown(1, $layer->unitCostCents);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @param InventoryCostLayer[] $layers */
    private function consumeLayers(array $layers, int $quantity): CostBreakdown
    {
        $remaining  = $quantity;
        $totalCost  = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $consumed   = $layer->consume($remaining);
            $totalCost += $consumed * $layer->unitCostCents;
            $remaining -= $consumed;
        }

        if ($remaining > 0) {
            throw new InsufficientInventoryException(
                'Insufficient cost layers to cover the sale quantity. '
                . 'Ledger and cost layers may be out of sync.'
            );
        }

        return new CostBreakdown($quantity, $totalCost);
    }
}
```

---

## 6. AccountingJournalService — The Translation Layer

```php
// Domain/Accounting/Services/AccountingJournalService.php
//
// Translates inventory domain events into journal entries.
// The accounting method determines which entries are created and when.
// This service knows nothing about inventory mechanics — it only knows
// about money and accounts.

class AccountingJournalService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        private readonly CostLayerService $costLayers,
    ) {}

    // -------------------------------------------------------------------------
    // Stock received from supplier
    // -------------------------------------------------------------------------

    public function onStockReceived(
        ProductVariantId $variantId,
        int $totalCostCents,
        string $purchaseOrderId,
        string $supplierName,
        \DateTimeImmutable $date,
        TenantAccountingConfig $config,
        TenantId $tenantId,
    ): ?JournalEntry {
        return match($config->accountingMethod) {

            AccountingMethod::Accrual => $this->createEntry(
                tenantId: $tenantId,
                date: $date,
                description: "Inventory received — PO {$purchaseOrderId}",
                referenceId: $purchaseOrderId,
                method: AccountingMethod::Accrual,
                lines: [
                    // Inventory asset increases
                    [AccountCode::inventory(), $totalCostCents, DebitCredit::Debit,
                        "Received from {$supplierName}"],
                    // AP liability created — we owe the supplier
                    [AccountCode::accountsPayable(), $totalCostCents, DebitCredit::Credit,
                        "AP — {$supplierName} — PO {$purchaseOrderId}"],
                ],
            ),

            // Cash method: no entry on receipt — defer until payment
            AccountingMethod::Cash => null,
        };
    }

    // -------------------------------------------------------------------------
    // Supplier invoice paid
    // -------------------------------------------------------------------------

    public function onSupplierPaid(
        int $amountCents,
        string $purchaseOrderId,
        string $supplierName,
        \DateTimeImmutable $date,
        TenantAccountingConfig $config,
        TenantId $tenantId,
    ): JournalEntry {
        return match($config->accountingMethod) {

            AccountingMethod::Accrual => $this->createEntry(
                tenantId: $tenantId,
                date: $date,
                description: "Supplier payment — {$supplierName}",
                referenceId: $purchaseOrderId,
                method: AccountingMethod::Accrual,
                lines: [
                    // Clear the AP liability
                    [AccountCode::accountsPayable(), $amountCents, DebitCredit::Debit,
                        "AP cleared — {$supplierName}"],
                    // Cash goes out
                    [AccountCode::cash(), $amountCents, DebitCredit::Credit,
                        "Payment to {$supplierName}"],
                ],
            ),

            AccountingMethod::Cash => $this->createEntry(
                tenantId: $tenantId,
                date: $date,
                description: "Inventory purchase — {$supplierName}",
                referenceId: $purchaseOrderId,
                method: AccountingMethod::Cash,
                lines: [
                    // Expense the inventory cost now (cash method — no AP, no asset)
                    [AccountCode::inventoryExpense(), $amountCents, DebitCredit::Debit,
                        "Inventory purchased from {$supplierName}"],
                    [AccountCode::cash(), $amountCents, DebitCredit::Credit,
                        "Payment to {$supplierName}"],
                ],
            ),
        };
    }

    // -------------------------------------------------------------------------
    // Stock sold
    // -------------------------------------------------------------------------

    public function onStockSold(
        ProductVariantId $variantId,
        int $quantity,
        int $salePriceCents,        // what the customer paid (or owes)
        bool $paymentReceivedNow,   // false for invoiced/credit sales
        ?string $customerName,
        string $saleId,
        \DateTimeImmutable $date,
        TenantAccountingConfig $config,
        TenantId $tenantId,
    ): JournalEntry {
        // Determine the cash/AR account based on whether payment was immediate
        $receivableAccount = $paymentReceivedNow
            ? AccountCode::cash()
            : AccountCode::accountsReceivable();

        $receivableMemo = $paymentReceivedNow
            ? 'Cash received'
            : "AR — {$customerName}";

        return match($config->accountingMethod) {

            AccountingMethod::Accrual => $this->createAccrualSaleEntry(
                variantId: $variantId,
                quantity: $quantity,
                salePriceCents: $salePriceCents,
                receivableAccount: $receivableAccount,
                receivableMemo: $receivableMemo,
                saleId: $saleId,
                date: $date,
                config: $config,
                tenantId: $tenantId,
            ),

            // Cash method: only recognize revenue when cash is received
            AccountingMethod::Cash => $paymentReceivedNow
                ? $this->createEntry(
                    tenantId: $tenantId,
                    date: $date,
                    description: "Sale — {$saleId}",
                    referenceId: $saleId,
                    method: AccountingMethod::Cash,
                    lines: [
                        [AccountCode::cash(), $salePriceCents, DebitCredit::Debit, 'Cash received'],
                        [AccountCode::salesRevenue(), $salePriceCents, DebitCredit::Credit, 'Revenue'],
                        // Note: NO COGS entry — inventory was expensed when the supplier was paid
                    ],
                )
                : null, // Cash method: no entry for credit sales until payment received
        };
    }

    // -------------------------------------------------------------------------
    // Customer payment received (accrual only — clears AR)
    // -------------------------------------------------------------------------

    public function onCustomerPaymentReceived(
        int $amountCents,
        string $invoiceId,
        string $customerName,
        \DateTimeImmutable $date,
        TenantAccountingConfig $config,
        TenantId $tenantId,
    ): ?JournalEntry {
        return match($config->accountingMethod) {

            AccountingMethod::Accrual => $this->createEntry(
                tenantId: $tenantId,
                date: $date,
                description: "Customer payment — {$customerName}",
                referenceId: $invoiceId,
                method: AccountingMethod::Accrual,
                lines: [
                    [AccountCode::cash(), $amountCents, DebitCredit::Debit, 'Cash received'],
                    [AccountCode::accountsReceivable(), $amountCents, DebitCredit::Credit,
                        "AR cleared — {$customerName}"],
                ],
            ),

            // Cash method: revenue was already recorded at payment time in onStockSold
            AccountingMethod::Cash => null,
        };
    }

    // -------------------------------------------------------------------------
    // Private — accrual sale (revenue + COGS)
    // -------------------------------------------------------------------------

    private function createAccrualSaleEntry(
        ProductVariantId $variantId,
        int $quantity,
        int $salePriceCents,
        AccountCode $receivableAccount,
        string $receivableMemo,
        string $saleId,
        \DateTimeImmutable $date,
        TenantAccountingConfig $config,
        TenantId $tenantId,
    ): JournalEntry {
        // Calculate COGS using the configured costing method
        $cogsCost = match($config->costingMethod) {
            CostingMethod::FIFO
                => $this->costLayers->consumeFifoLayers($variantId, $quantity),
            CostingMethod::WeightedAverageCost
                => $this->costLayers->calculateWeightedAverageCost($variantId, $quantity),
            CostingMethod::SpecificIdentification
                => throw new \LogicException(
                    'SpecificIdentification requires a serial number. '
                    . 'Use onSerializedItemSold() instead.'
                ),
        };

        return $this->createEntry(
            tenantId: $tenantId,
            date: $date,
            description: "Sale — {$saleId}",
            referenceId: $saleId,
            method: AccountingMethod::Accrual,
            lines: [
                // Revenue side
                [$receivableAccount,          $salePriceCents,          DebitCredit::Debit,  $receivableMemo],
                [AccountCode::salesRevenue(), $salePriceCents,          DebitCredit::Credit, 'Sales revenue'],
                // COGS side
                [AccountCode::costOfGoodsSold(), $cogsCost->totalCostCents, DebitCredit::Debit,  'COGS'],
                [AccountCode::inventory(),       $cogsCost->totalCostCents, DebitCredit::Credit, 'Inventory reduction'],
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Private — factory for creating and persisting a balanced entry
    // -------------------------------------------------------------------------

    private function createEntry(
        TenantId $tenantId,
        \DateTimeImmutable $date,
        string $description,
        ?string $referenceId,
        AccountingMethod $method,
        array $lines, // [AccountCode, amountCents, DebitCredit, memo]
    ): JournalEntry {
        $entry = new JournalEntry(
            id: JournalEntryId::generate(),
            tenantId: $tenantId,
            date: $date,
            description: $description,
            referenceId: $referenceId,
            method: $method,
        );

        foreach ($lines as [$account, $amount, $type, $memo]) {
            $entry->addLine($account, $amount, $type, $memo);
        }

        $entry->assertBalanced(); // always validate before persisting

        $this->journal->save($entry);

        return $entry;
    }
}
```

---

## 7. Event Listeners — Wiring Inventory to Accounting

```php
// Domain/Accounting/Listeners/OnStockReceived.php
//
// Subscribes to the inventory domain event and delegates to the journal service.
// The listener fetches tenant config — the service doesn't need to know about it.

class OnStockReceived
{
    public function __construct(
        private readonly AccountingJournalService $journal,
        private readonly TenantRepository $tenants,
    ) {}

    public function handle(StockReceivedEvent $event): void
    {
        $tenant = $this->tenants->findOrFail($event->tenantId);
        $config = $tenant->accountingConfig();

        $this->journal->onStockReceived(
            variantId: $event->variantId,
            totalCostCents: $event->totalCostCents,
            purchaseOrderId: $event->purchaseOrderId,
            supplierName: $event->supplierName,
            date: $event->occurredAt,
            config: $config,
            tenantId: $event->tenantId,
        );
    }
}
```

```php
// Register in EventServiceProvider.php

protected $listen = [
    StockReceivedEvent::class  => [OnStockReceived::class],
    StockSoldEvent::class      => [OnStockSold::class],
    SupplierPaidEvent::class   => [OnSupplierPaid::class],
    CustomerPaidEvent::class   => [OnCustomerPaymentReceived::class],
];
```

---

## 8. Side-by-Side: Same Transaction, Different Accounting

```php
// Scenario: Receive 10 units at $5.00 each, then sell 3 at $12.00 each.
// Supplier is paid 30 days later.

// === ACCRUAL ACCOUNTING ===

// Event 1: Stock received
// DR Inventory        $50.00
// CR Accounts Payable $50.00

// Event 2: Stock sold (cash at POS, FIFO costing)
// DR Cash             $36.00
// CR Sales Revenue    $36.00
// DR COGS             $15.00   (3 units × $5.00)
// CR Inventory        $15.00

// Event 3: Supplier paid
// DR Accounts Payable $50.00
// CR Cash             $50.00

// Net result: Inventory asset = $35.00 (7 units × $5.00), Revenue = $36.00, COGS = $15.00
// Gross margin clearly visible: $21.00 on $36.00 revenue = 58.3%


// === CASH ACCOUNTING ===

// Event 1: Stock received
// (no entry)

// Event 2: Stock sold (cash at POS)
// DR Cash          $36.00
// CR Sales Revenue $36.00
// (no COGS entry — cost not yet recognized)

// Event 3: Supplier paid
// DR Inventory Expense $50.00   (all 10 units expensed at payment time)
// CR Cash              $50.00

// Net result: Revenue = $36.00, Expense = $50.00 this period
// Gross margin is misleading — 7 units still in inventory but expensed
// This is why cash accounting distorts profitability analysis for inventory businesses
```

---

## 9. Database Schema

```php
Schema::create('journal_entries', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id')->index();
    $table->date('date')->index();
    $table->string('description');
    $table->string('reference_id')->nullable()->index();
    $table->string('accounting_method', 10);
    $table->timestamps();
});

Schema::create('journal_lines', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('journal_entry_id')->index();
    $table->string('account_code', 20);
    $table->string('account_name');
    $table->string('account_category', 20);
    $table->unsignedBigInteger('amount_cents');
    $table->string('type', 10);  // 'debit' | 'credit'
    $table->string('memo')->nullable();

    $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
});

Schema::create('inventory_cost_layers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id')->index();
    $table->uuid('variant_id')->index();
    $table->integer('original_quantity');
    $table->integer('remaining_quantity');
    $table->unsignedBigInteger('unit_cost_cents');
    $table->string('purchase_order_id')->nullable()->index();
    $table->timestamp('received_at')->index();
    $table->timestamps();
});
```

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| Accounting is a separate bounded context | The inventory ledger tracks units; the journal tracks money. Mixing them creates a context that's hard to reason about and hard to replace when tenants integrate QuickBooks. |
| Journal entries driven by domain events | The inventory system doesn't know or care about accounting method — it raises events, and the accounting context reacts |
| `assertBalanced()` before every persist | An unbalanced journal entry is always a programming error, never a data issue. Fail loudly at the domain layer. |
| Cost layers are separate from the inventory ledger | The ledger tracks how many units exist. Cost layers track what those units cost. They're related but distinct — the ledger can be correct while cost layers drift, which `isConsistentWithLedger()` can detect |
| Cash method returns `null` for some events | Returning null (no journal entry) is an explicit, intentional choice — not an oversight. The caller decides what to do with null. |
| Cash method COGS note in the side-by-side | Cash accounting overstates expenses in the period of purchase and understates them in periods before purchase — important for tenants to understand |
