# Modeling the Opening Balance

## The Core Problem

Opening balance isn't just a ledger entry with a different reason code — it has
unique invariants that justify its own aggregate:

- It can only be posted **once per variant per location**
- Merchants typically enter counts **gradually** before going live, not all at once
- It needs an **as-of date** that may differ from when it's entered
- It should be **lockable** — once submitted, no further changes
- It should post **atomically** — all variants or none

A simple `InventoryService::setOpeningBalance()` method won't express these rules
cleanly. A `StockOnboarding` aggregate does.

---

## Directory Structure

```
app/
└── Domain/
    └── Inventory/
        ├── Aggregates/
        │   └── StockOnboarding.php
        ├── ValueObjects/
        │   └── StockOnboardingItem.php
        ├── Enums/
        │   └── StockOnboardingStatus.php
        ├── Events/
        │   ├── StockOnboardingSubmitted.php
        │   └── OpeningBalancePosted.php
        ├── Exceptions/
        │   ├── OpeningBalanceConflictException.php
        │   └── OnboardingAlreadySubmittedException.php
        └── Services/
            └── OpeningBalanceService.php
```

---

## 1. Status Enum

```php
// Domain/Inventory/Enums/StockOnboardingStatus.php

enum StockOnboardingStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
}
```

---

## 2. StockOnboardingItem Value Object

```php
// Domain/Inventory/ValueObjects/StockOnboardingItem.php
//
// Immutable snapshot of a single variant's opening quantity and cost.
// Quantity of zero is allowed — it explicitly records "this SKU exists
// but has no stock," which is different from the variant being absent.

final class StockOnboardingItem
{
    public function __construct(
        public readonly ProductVariantId $variantId,
        public readonly int $quantity,
        public readonly int $unitCostCents, // store cost in cents to avoid float precision issues
    ) {
        if ($this->quantity < 0) {
            throw new \InvalidArgumentException('Opening balance quantity cannot be negative.');
        }
        if ($this->unitCostCents < 0) {
            throw new \InvalidArgumentException('Unit cost cannot be negative.');
        }
    }
}
```

---

## 3. StockOnboarding Aggregate

```php
// Domain/Inventory/Aggregates/StockOnboarding.php
//
// Represents a merchant's intent to establish initial inventory for a location.
// Lifecycle: Draft → Submitted (terminal, no further transitions).

class StockOnboarding
{
    private StockOnboardingStatus $status;

    /** @var StockOnboardingItem[] indexed by variantId string for fast lookup */
    private array $items = [];

    private array $domainEvents = [];

    public function __construct(
        public readonly StockOnboardingId $id,
        public readonly TenantId $tenantId,
        public readonly LocationId $locationId,
        public readonly \DateTimeImmutable $asOfDate,
    ) {
        $this->status = StockOnboardingStatus::Draft;
    }

    // -------------------------------------------------------------------------
    // Mutations — only allowed while in Draft
    // -------------------------------------------------------------------------

    /**
     * Set or replace a variant's opening quantity and cost.
     * Calling this again for the same variant overwrites the previous entry,
     * allowing corrections before submission.
     */
    public function setItem(
        ProductVariantId $variantId,
        int $quantity,
        int $unitCostCents,
    ): void {
        $this->assertDraft();

        $this->items[$variantId->value] = new StockOnboardingItem(
            variantId: $variantId,
            quantity: $quantity,
            unitCostCents: $unitCostCents,
        );
    }

    /**
     * Remove a variant from the onboarding before submission.
     */
    public function removeItem(ProductVariantId $variantId): void
    {
        $this->assertDraft();
        unset($this->items[$variantId->value]);
    }

    /**
     * Lock the onboarding. After submission, items cannot be changed.
     * This raises a domain event that the OpeningBalanceService listens to.
     */
    public function submit(): void
    {
        $this->assertDraft();

        if (empty($this->items)) {
            throw new \DomainException('Cannot submit a stock onboarding with no items.');
        }

        $this->status = StockOnboardingStatus::Submitted;

        $this->domainEvents[] = new StockOnboardingSubmitted($this->id, $this->locationId);
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function status(): StockOnboardingStatus
    {
        return $this->status;
    }

    public function isSubmitted(): bool
    {
        return $this->status === StockOnboardingStatus::Submitted;
    }

    /** @return StockOnboardingItem[] */
    public function items(): array
    {
        return array_values($this->items);
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // -------------------------------------------------------------------------
    // Invariant guard
    // -------------------------------------------------------------------------

    private function assertDraft(): void
    {
        if ($this->status !== StockOnboardingStatus::Draft) {
            throw new OnboardingAlreadySubmittedException(
                "Onboarding {$this->id->value} has been submitted and is immutable."
            );
        }
    }
}
```

---

## 4. OpeningBalanceService

```php
// Domain/Inventory/Services/OpeningBalanceService.php
//
// Processes a submitted StockOnboarding and posts opening balance ledger entries.
// Two-pass pattern: validate all variants have no existing entries first,
// then write all entries — same atomicity principle as kit decrement.

class OpeningBalanceService
{
    public function __construct(
        private readonly LedgerRepositoryInterface $ledger,
        private readonly EventDispatcherInterface $events,
    ) {}

    public function process(StockOnboarding $onboarding, ActorId $actor): void
    {
        if (!$onboarding->isSubmitted()) {
            throw new \DomainException(
                'Only submitted onboardings can be processed. Call submit() first.'
            );
        }

        // --- Pass 1: Guard against duplicate opening balances ---
        //
        // If ANY variant already has ledger entries at this location,
        // reject the entire onboarding. This prevents partial re-processing
        // if the command is accidentally replayed.
        foreach ($onboarding->items() as $item) {
            if ($this->ledger->hasAnyEntries($item->variantId, $onboarding->locationId)) {
                throw new OpeningBalanceConflictException(
                    variantId: $item->variantId,
                    locationId: $onboarding->locationId,
                );
            }
        }

        // --- Pass 2: Post ledger entries ---
        //
        // Key detail: use $onboarding->asOfDate as occurredAt, NOT the current
        // timestamp. Merchants often enter opening balances days after go-live.
        // Using asOfDate keeps the ledger historically accurate.
        foreach ($onboarding->items() as $item) {
            $this->ledger->append(new LedgerEntry(
                id: LedgerEntryId::generate(),
                variantId: $item->variantId,
                quantity: $item->quantity,   // positive — stock coming in
                reason: ReasonCode::OpeningBalance,
                actor: $actor,
                referenceId: $onboarding->id->value,
                occurredAt: $onboarding->asOfDate,
                metadata: [
                    'unitCostCents' => $item->unitCostCents,
                    'locationId'    => $onboarding->locationId->value,
                ],
            ));

            $this->events->dispatch(
                new OpeningBalancePosted($item->variantId, $item->quantity, $onboarding->id)
            );
        }
    }
}
```

---

## 5. LedgerRepository — New Method Required

```php
// Add to LedgerRepositoryInterface

interface LedgerRepositoryInterface
{
    // ... existing methods ...

    /**
     * Returns true if any ledger entries exist for this variant at this location.
     * Used to guard against duplicate opening balance postings.
     */
    public function hasAnyEntries(ProductVariantId $variantId, LocationId $locationId): bool;
}
```

```php
// Eloquent implementation (example)

public function hasAnyEntries(ProductVariantId $variantId, LocationId $locationId): bool
{
    return LedgerEntryModel::query()
        ->where('variant_id', $variantId->value)
        ->where('location_id', $locationId->value)
        ->exists();
}
```

---

## 6. Application Layer

```php
// Application/Inventory/Commands/SubmitStockOnboardingCommand.php

readonly class SubmitStockOnboardingCommand
{
    public function __construct(
        public string $onboardingId,
        public string $actorId,
    ) {}
}
```

```php
// Application/Inventory/Handlers/SubmitStockOnboardingHandler.php
//
// Orchestrates submission + processing in a single DB transaction.
// The domain events released from the aggregate are dispatched after commit.

class SubmitStockOnboardingHandler
{
    public function __construct(
        private readonly StockOnboardingRepository $onboardings,
        private readonly OpeningBalanceService $openingBalanceService,
        private readonly \Illuminate\Database\ConnectionInterface $db,
    ) {}

    public function handle(SubmitStockOnboardingCommand $command): void
    {
        $onboarding = $this->onboardings->findOrFail(
            StockOnboardingId::from($command->onboardingId)
        );

        $actor = ActorId::from($command->actorId);

        $this->db->transaction(function () use ($onboarding, $actor) {
            $onboarding->submit();
            $this->onboardings->save($onboarding);

            $this->openingBalanceService->process($onboarding, $actor);
        });

        // Dispatch domain events after the transaction commits
        foreach ($onboarding->releaseEvents() as $event) {
            event($event);
        }
    }
}
```

---

## 7. Usage — Full Lifecycle

```php
// Step 1: Create the onboarding record (e.g., in an API controller or command)
$onboarding = new StockOnboarding(
    id: StockOnboardingId::generate(),
    tenantId: TenantId::from($tenantId),
    locationId: LocationId::from($locationId),
    asOfDate: new \DateTimeImmutable('2024-11-01'), // merchant's declared go-live date
);

$onboardingRepository->save($onboarding);

// Step 2: Merchant enters counts (can happen across multiple requests/sessions)
$onboarding->setItem($variantSmallRed->id, quantity: 24, unitCostCents: 1499);
$onboarding->setItem($variantMediumBlue->id, quantity: 18, unitCostCents: 1499);
$onboarding->setItem($variantLargeGreen->id, quantity: 0, unitCostCents: 1499); // in catalog, zero stock

// Merchant realizes they counted Small/Red wrong — overwrite before submitting
$onboarding->setItem($variantSmallRed->id, quantity: 22, unitCostCents: 1499);

$onboardingRepository->save($onboarding);

// Step 3: Merchant submits — triggers processing via the command handler
$handler->handle(new SubmitStockOnboardingCommand(
    onboardingId: $onboarding->id->value,
    actorId: $currentUser->id,
));

// Result: three ledger entries posted with ReasonCode::OpeningBalance,
// all with occurredAt = 2024-11-01, all referencing the onboarding ID.
```

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| `asOfDate` separate from processing date | Merchants set up systems days after go-live; the ledger should reflect when stock was actually counted, not when it was entered |
| Zero-quantity items allowed | Distinguishes "SKU exists with no stock" from "SKU was never configured" — both matter for reporting |
| `setItem` overwrites previous entry | Merchants need to correct counts before submission; a replace-on-duplicate model is simpler than a separate update path |
| Submitted state is terminal | No "undo" path — corrections after the fact use `CountAdjustment` entries, which preserves the audit trail |
| Two-pass conflict check | Prevents partial re-processing if the submit command fires twice (e.g., network retry) |
| `referenceId` = onboarding ID | All opening entries for a location are tied together and queryable as a single onboarding event |
