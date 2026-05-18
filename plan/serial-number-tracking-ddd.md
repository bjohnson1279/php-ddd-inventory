# Serial Number Tracking

## The Core Problem

Serial number tracking adds a second dimension to inventory. The ledger asks
*how many* units are in stock. Serial tracking asks *which specific units* are
in stock — and where each one has been.

The two systems must stay in sync:

- Ledger quantity **=** count of `InStock` serialized items for that variant
- Every state transition on a `SerializedItem` that changes sellability **must**
  produce a corresponding ledger entry

Not all variants need serial tracking. The model needs to accommodate three
distinct tracking modes, and serial logic should only activate when the variant
is configured for it.

---

## Tracking Modes

```php
// Domain/Inventory/Enums/VariantTrackingMode.php

enum VariantTrackingMode: string
{
    case Quantity = 'quantity'; // Default — track totals only
    case Lot      = 'lot';      // Group tracking with expiry (food, pharma)
    case Serial   = 'serial';   // Individual unit tracking (electronics, jewelry)
}
```

This is set on the `ProductVariant` entity during product configuration. The
inventory service checks this before deciding which code path to execute.

---

## Directory Structure

```
app/
└── Domain/
    └── Serial/
        ├── Aggregates/
        │   └── SerializedItem.php
        ├── ValueObjects/
        │   ├── SerialNumber.php
        │   └── StatusTransition.php
        ├── Enums/
        │   └── SerializedItemStatus.php
        ├── Events/
        │   ├── SerializedItemReceived.php
        │   ├── SerializedItemSold.php
        │   └── SerializedItemReturned.php
        ├── Exceptions/
        │   ├── SerialNumberAlreadyRegisteredException.php
        │   ├── SerialNumberNotFoundException.php
        │   └── InvalidSerialStatusTransitionException.php
        ├── Services/
        │   └── SerializedInventoryService.php
        └── Repositories/
            └── SerializedItemRepositoryInterface.php
```

---

## 1. SerialNumber Value Object

```php
// Domain/Serial/ValueObjects/SerialNumber.php
//
// The serial number is just a validated string. Format rules are
// intentionally loose here — real-world serials vary wildly by
// manufacturer (IMEIs, MAC addresses, alphanumeric strings, etc.).
// Per-product format validation is handled at the variant config layer
// if needed.

final class SerialNumber
{
    public readonly string $value;

    public function __construct(string $raw)
    {
        $normalized = strtoupper(trim($raw));

        if (empty($normalized)) {
            throw new \InvalidArgumentException('Serial number cannot be empty.');
        }

        if (strlen($normalized) > 100) {
            throw new \InvalidArgumentException('Serial number cannot exceed 100 characters.');
        }

        // Allow alphanumeric, hyphens, dots, and forward slashes —
        // covers most manufacturer formats without being overly restrictive
        if (!preg_match('/^[A-Z0-9\-\.\/]+$/', $normalized)) {
            throw new \InvalidArgumentException(
                "Serial number contains invalid characters: {$normalized}"
            );
        }

        $this->value = $normalized;
    }

    public function equals(SerialNumber $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

---

## 2. SerializedItemStatus Enum + Transition Rules

```php
// Domain/Serial/Enums/SerializedItemStatus.php
//
// The state machine is the core of serial tracking.
// Every status change must follow a valid path — invalid transitions
// are a domain violation, not just a validation error.

enum SerializedItemStatus: string
{
    case Pending     = 'pending';     // Registered on a PO, not yet physically received
    case InStock     = 'in_stock';    // Available and sellable
    case Sold        = 'sold';        // Sold to a customer — not in stock
    case Returned    = 'returned';    // Back from a customer, pending inspection
    case Quarantined = 'quarantined'; // Held — awaiting inspection or repair
    case Transferred = 'transferred'; // In transit between locations
    case Damaged     = 'damaged';     // Damaged — not sellable without repair
    case WrittenOff  = 'written_off'; // Permanently removed from inventory

    /**
     * Defines all valid state transitions.
     * Any transition not in this map is a domain violation.
     *
     * @return SerializedItemStatus[]
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending => [
                self::InStock,      // Received in good condition
                self::Damaged,      // Arrived damaged
                self::Quarantined,  // Arrived, held for inspection
            ],
            self::InStock => [
                self::Sold,
                self::Damaged,
                self::Quarantined,
                self::Transferred,
                self::WrittenOff,
            ],
            self::Sold => [
                self::Returned,     // Customer return
            ],
            self::Returned => [
                self::InStock,      // Inspected, sellable
                self::Damaged,      // Inspected, damaged
                self::WrittenOff,   // Inspected, unrecoverable
                self::Quarantined,  // Needs further inspection
            ],
            self::Quarantined => [
                self::InStock,      // Cleared
                self::Damaged,      // Failed inspection
                self::WrittenOff,
            ],
            self::Transferred => [
                self::InStock,      // Arrived at destination
                self::Damaged,      // Damaged in transit
            ],
            self::Damaged => [
                self::Quarantined,  // Sent for repair/assessment
                self::WrittenOff,
            ],
            self::WrittenOff => [], // Terminal state — no further transitions
        };
    }

    public function canTransitionTo(SerializedItemStatus $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Whether this status counts as available inventory.
     * Used to keep the ledger in sync.
     */
    public function isCountedInStock(): bool
    {
        return $this === self::InStock;
    }

    /**
     * Whether transitioning TO this status should produce a ledger entry.
     * Transitions that move a unit into or out of sellable stock need
     * a corresponding ledger write.
     */
    public function requiresLedgerEntry(SerializedItemStatus $from): bool
    {
        $enteringStock = $this === self::InStock && !$from->isCountedInStock();
        $leavingStock  = !$this->isCountedInStock() && $from->isCountedInStock();

        return $enteringStock || $leavingStock;
    }
}
```

---

## 3. StatusTransition Value Object (History Entry)

```php
// Domain/Serial/ValueObjects/StatusTransition.php
//
// Every status change is recorded as an immutable history entry.
// The full history is the audit trail — never mutate entries.

final class StatusTransition
{
    public function __construct(
        public readonly SerializedItemStatus $from,
        public readonly SerializedItemStatus $to,
        public readonly string $reason,
        public readonly ActorId $actor,
        public readonly ?string $referenceId,  // saleId, poId, returnId, etc.
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
```

---

## 4. SerializedItem Aggregate

```php
// Domain/Serial/Aggregates/SerializedItem.php
//
// One aggregate instance per physical unit.
// The aggregate owns its state machine and history.
// It does NOT write ledger entries directly — it raises events and
// returns information about whether a ledger write is required.
// SerializedInventoryService coordinates the two.

class SerializedItem
{
    private SerializedItemStatus $status;

    /** @var StatusTransition[] */
    private array $history = [];

    private array $domainEvents = [];

    public function __construct(
        public readonly SerializedItemId $id,
        public readonly ProductVariantId $variantId,
        public readonly SerialNumber $serialNumber,
        public readonly TenantId $tenantId,
        private LocationId $locationId,
        SerializedItemStatus $initialStatus = SerializedItemStatus::Pending,
    ) {
        $this->status = $initialStatus;
    }

    // -------------------------------------------------------------------------
    // State transitions — each named for the business action, not the state
    // -------------------------------------------------------------------------

    public function receive(LocationId $location, ActorId $actor, string $purchaseOrderId): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::InStock,
            reason: "Received against PO {$purchaseOrderId}",
            actor: $actor,
            referenceId: $purchaseOrderId,
        );
        $this->locationId = $location;
    }

    public function sell(string $saleId, ActorId $actor): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::Sold,
            reason: "Sold — sale {$saleId}",
            actor: $actor,
            referenceId: $saleId,
        );
    }

    public function acceptReturn(string $returnId, ActorId $actor): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::Returned,
            reason: "Customer return — {$returnId}",
            actor: $actor,
            referenceId: $returnId,
        );
    }

    public function restock(ActorId $actor, string $returnId): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::InStock,
            reason: "Restocked after inspection — return {$returnId}",
            actor: $actor,
            referenceId: $returnId,
        );
    }

    public function markDamaged(string $reason, ActorId $actor, ?string $referenceId = null): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::Damaged,
            reason: $reason,
            actor: $actor,
            referenceId: $referenceId,
        );
    }

    public function quarantine(string $reason, ActorId $actor, ?string $referenceId = null): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::Quarantined,
            reason: $reason,
            actor: $actor,
            referenceId: $referenceId,
        );
    }

    public function transferOut(LocationId $destination, ActorId $actor, string $transferId): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::Transferred,
            reason: "Transfer out to {$destination->value} — {$transferId}",
            actor: $actor,
            referenceId: $transferId,
        );
    }

    public function transferIn(LocationId $newLocation, ActorId $actor, string $transferId): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::InStock,
            reason: "Transfer in — {$transferId}",
            actor: $actor,
            referenceId: $transferId,
        );
        $this->locationId = $newLocation;
    }

    public function writeOff(string $reason, ActorId $actor, ?string $referenceId = null): void
    {
        $this->transitionTo(
            target: SerializedItemStatus::WrittenOff,
            reason: $reason,
            actor: $actor,
            referenceId: $referenceId,
        );
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function status(): SerializedItemStatus
    {
        return $this->status;
    }

    public function locationId(): LocationId
    {
        return $this->locationId;
    }

    public function isAvailable(): bool
    {
        return $this->status === SerializedItemStatus::InStock;
    }

    /** @return StatusTransition[] */
    public function history(): array
    {
        return $this->history;
    }

    public function lastTransition(): ?StatusTransition
    {
        return !empty($this->history) ? end($this->history) : null;
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // -------------------------------------------------------------------------
    // Private transition engine
    // -------------------------------------------------------------------------

    private function transitionTo(
        SerializedItemStatus $target,
        string $reason,
        ActorId $actor,
        ?string $referenceId,
    ): void {
        if (!$this->status->canTransitionTo($target)) {
            throw new InvalidSerialStatusTransitionException(
                serialNumber: $this->serialNumber,
                from: $this->status,
                to: $target,
            );
        }

        $transition = new StatusTransition(
            from: $this->status,
            to: $target,
            reason: $reason,
            actor: $actor,
            referenceId: $referenceId,
            occurredAt: new \DateTimeImmutable(),
        );

        $this->history[] = $transition;
        $this->status    = $target;

        $this->domainEvents[] = new SerialStatusChanged(
            itemId: $this->id,
            serialNumber: $this->serialNumber,
            transition: $transition,
            requiresLedgerEntry: $target->requiresLedgerEntry($transition->from),
        );
    }
}
```

---

## 5. Repository Interface

```php
// Domain/Serial/Repositories/SerializedItemRepositoryInterface.php

interface SerializedItemRepositoryInterface
{
    public function findBySerial(SerialNumber $serial, TenantId $tenantId): ?SerializedItem;

    public function findBySerialOrFail(SerialNumber $serial, TenantId $tenantId): SerializedItem;

    public function findById(SerializedItemId $id): ?SerializedItem;

    /** @return SerializedItem[] */
    public function findByVariant(ProductVariantId $variantId, ?SerializedItemStatus $status = null): array;

    public function isRegistered(SerialNumber $serial, TenantId $tenantId): bool;

    public function countByStatus(ProductVariantId $variantId, SerializedItemStatus $status): int;

    public function save(SerializedItem $item): void;
}
```

---

## 6. SerializedInventoryService

```php
// Domain/Serial/Services/SerializedInventoryService.php
//
// Coordinates the two systems that must stay in sync:
//   1. SerializedItem state machine (which serial is in what status)
//   2. InventoryLedger (how many units are in stock)
//
// Every operation that changes sellability writes BOTH.
// The application layer wraps calls in a DB transaction.

class SerializedInventoryService
{
    public function __construct(
        private readonly SerializedItemRepositoryInterface $serials,
        private readonly LedgerRepositoryInterface $ledger,
        private readonly EventDispatcherInterface $events,
    ) {}

    // -------------------------------------------------------------------------
    // Register a serial before physical receipt (e.g. from a PO manifest)
    // -------------------------------------------------------------------------

    public function register(
        SerialNumber $serialNumber,
        ProductVariantId $variantId,
        TenantId $tenantId,
        LocationId $locationId,
        ActorId $actor,
    ): SerializedItem {
        if ($this->serials->isRegistered($serialNumber, $tenantId)) {
            throw new SerialNumberAlreadyRegisteredException($serialNumber);
        }

        $item = new SerializedItem(
            id: SerializedItemId::generate(),
            variantId: $variantId,
            serialNumber: $serialNumber,
            tenantId: $tenantId,
            locationId: $locationId,
            initialStatus: SerializedItemStatus::Pending,
        );

        $this->serials->save($item);

        return $item;
    }

    // -------------------------------------------------------------------------
    // Receive — Pending → InStock + ledger +1
    // -------------------------------------------------------------------------

    public function receive(
        SerialNumber $serialNumber,
        TenantId $tenantId,
        LocationId $location,
        string $purchaseOrderId,
        int $unitCostCents,
        ActorId $actor,
    ): void {
        $item = $this->serials->findBySerialOrFail($serialNumber, $tenantId);

        $item->receive($location, $actor, $purchaseOrderId);

        $this->ledger->append(new LedgerEntry(
            id: LedgerEntryId::generate(),
            variantId: $item->variantId,
            quantity: +1,
            reason: ReasonCode::PurchaseReceipt,
            actor: $actor,
            referenceId: $purchaseOrderId,
            occurredAt: new \DateTimeImmutable(),
            metadata: [
                'serialNumber'  => $serialNumber->value,
                'locationId'    => $location->value,
                'unitCostCents' => $unitCostCents,
            ],
        ));

        $this->serials->save($item);
        $this->dispatchEvents($item);
    }

    // -------------------------------------------------------------------------
    // Sell — InStock → Sold + ledger -1
    // -------------------------------------------------------------------------

    public function sell(
        SerialNumber $serialNumber,
        TenantId $tenantId,
        string $saleId,
        ActorId $actor,
    ): void {
        $item = $this->serials->findBySerialOrFail($serialNumber, $tenantId);

        // The state machine enforces this — sell() throws
        // InvalidSerialStatusTransitionException if not InStock
        $item->sell($saleId, $actor);

        $this->ledger->append(new LedgerEntry(
            id: LedgerEntryId::generate(),
            variantId: $item->variantId,
            quantity: -1,
            reason: ReasonCode::Sale,
            actor: $actor,
            referenceId: $saleId,
            occurredAt: new \DateTimeImmutable(),
            metadata: ['serialNumber' => $serialNumber->value],
        ));

        $this->serials->save($item);
        $this->dispatchEvents($item);
    }

    // -------------------------------------------------------------------------
    // Return — Sold → Returned (no ledger change yet — not back in stock yet)
    // -------------------------------------------------------------------------

    public function acceptReturn(
        SerialNumber $serialNumber,
        TenantId $tenantId,
        string $returnId,
        ActorId $actor,
    ): void {
        $item = $this->serials->findBySerialOrFail($serialNumber, $tenantId);

        $item->acceptReturn($returnId, $actor);

        // No ledger entry here — the item is not sellable yet.
        // The ledger entry comes when the item is restocked or written off.

        $this->serials->save($item);
        $this->dispatchEvents($item);
    }

    // -------------------------------------------------------------------------
    // Restock after inspection — Returned → InStock + ledger +1
    // -------------------------------------------------------------------------

    public function restock(
        SerialNumber $serialNumber,
        TenantId $tenantId,
        string $returnId,
        int $restockedUnitCostCents,
        ActorId $actor,
    ): void {
        $item = $this->serials->findBySerialOrFail($serialNumber, $tenantId);

        $item->restock($actor, $returnId);

        $this->ledger->append(new LedgerEntry(
            id: LedgerEntryId::generate(),
            variantId: $item->variantId,
            quantity: +1,
            reason: ReasonCode::Return,
            actor: $actor,
            referenceId: $returnId,
            occurredAt: new \DateTimeImmutable(),
            metadata: [
                'serialNumber'           => $serialNumber->value,
                'restockedUnitCostCents' => $restockedUnitCostCents,
            ],
        ));

        $this->serials->save($item);
        $this->dispatchEvents($item);
    }

    // -------------------------------------------------------------------------
    // Write off — only writes a ledger entry if the item was InStock
    // -------------------------------------------------------------------------

    public function writeOff(
        SerialNumber $serialNumber,
        TenantId $tenantId,
        string $reason,
        ActorId $actor,
        ?string $referenceId = null,
    ): void {
        $item = $this->serials->findBySerialOrFail($serialNumber, $tenantId);

        $wasInStock = $item->status()->isCountedInStock();

        $item->writeOff($reason, $actor, $referenceId);

        // Only deduct from the ledger if the item was previously sellable
        if ($wasInStock) {
            $this->ledger->append(new LedgerEntry(
                id: LedgerEntryId::generate(),
                variantId: $item->variantId,
                quantity: -1,
                reason: ReasonCode::WriteOff,
                actor: $actor,
                referenceId: $referenceId,
                occurredAt: new \DateTimeImmutable(),
                metadata: ['serialNumber' => $serialNumber->value],
            ));
        }

        $this->serials->save($item);
        $this->dispatchEvents($item);
    }

    // -------------------------------------------------------------------------
    // Consistency check — run as a scheduled reconciliation job
    // -------------------------------------------------------------------------

    public function isConsistentWithLedger(ProductVariantId $variantId): bool
    {
        $ledgerQty    = $this->ledger->currentQuantity($variantId);
        $inStockCount = $this->serials->countByStatus($variantId, SerializedItemStatus::InStock);

        return $ledgerQty === $inStockCount;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function dispatchEvents(SerializedItem $item): void
    {
        foreach ($item->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
```

---

## 7. Bulk Receiving (Multiple Serials from One PO)

```php
// Application/Serial/Handlers/ReceiveSerializedShipmentHandler.php
//
// Two-pass pattern: validate all serials before writing anything.

class ReceiveSerializedShipmentHandler
{
    public function __construct(
        private readonly SerializedInventoryService $serialService,
        private readonly SerializedItemRepositoryInterface $serials,
        private readonly \Illuminate\Database\ConnectionInterface $db,
    ) {}

    public function handle(ReceiveSerializedShipmentCommand $command): void
    {
        // Pass 1: validate all serials exist and are Pending
        foreach ($command->serialNumbers as $rawSerial) {
            $serial   = new SerialNumber($rawSerial);
            $existing = $this->serials->findBySerial($serial, TenantId::from($command->tenantId));

            if ($existing === null) {
                throw new SerialNumberNotFoundException(
                    "Serial {$serial->value} not found. Register it on the PO first."
                );
            }

            if ($existing->status() !== SerializedItemStatus::Pending) {
                throw new \DomainException(
                    "Serial {$serial->value} is already {$existing->status()->value}. "
                    . 'Expected Pending status for receiving.'
                );
            }
        }

        // Pass 2: receive all serials in a single transaction
        $this->db->transaction(function () use ($command) {
            foreach ($command->serialNumbers as $rawSerial) {
                $this->serialService->receive(
                    serialNumber: new SerialNumber($rawSerial),
                    tenantId: TenantId::from($command->tenantId),
                    location: LocationId::from($command->locationId),
                    purchaseOrderId: $command->purchaseOrderId,
                    unitCostCents: $command->unitCostCents,
                    actor: ActorId::from($command->actorId),
                );
            }
        });
    }
}
```

---

## 8. Database Schema

```php
Schema::create('serialized_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id')->index();
    $table->uuid('variant_id')->index();
    $table->string('serial_number', 100);
    $table->string('status', 20)->index();
    $table->uuid('location_id')->nullable()->index();
    $table->timestamps();

    $table->unique(['tenant_id', 'serial_number']);
    $table->foreign('variant_id')->references('id')->on('product_variants');
});

Schema::create('serial_status_transitions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('serialized_item_id')->index();
    $table->string('from_status', 20);
    $table->string('to_status', 20);
    $table->string('reason');
    $table->uuid('actor_id');
    $table->string('reference_id')->nullable()->index();
    $table->timestamp('occurred_at');

    $table->foreign('serialized_item_id')->references('id')->on('serialized_items');
});
```

---

## 9. Full Lifecycle Example

```php
// 1. Register serials against a PO before shipment arrives
$serialService->register(new SerialNumber('SN-ABC-001'), $variantId, $tenantId, $locationId, $actor);
$serialService->register(new SerialNumber('SN-ABC-002'), $variantId, $tenantId, $locationId, $actor);

// 2. Shipment arrives — receive each serial (Pending → InStock, ledger +1 each)
$serialService->receive(new SerialNumber('SN-ABC-001'), $tenantId, $locationId, $poId, 4999, $actor);
$serialService->receive(new SerialNumber('SN-ABC-002'), $tenantId, $locationId, $poId, 4999, $actor);
// Ledger: +2

// 3. Sell one (InStock → Sold, ledger -1)
$serialService->sell(new SerialNumber('SN-ABC-001'), $tenantId, $saleId, $cashierActor);
// Ledger: +1

// 4. Customer returns it (Sold → Returned, no ledger change)
$serialService->acceptReturn(new SerialNumber('SN-ABC-001'), $tenantId, $returnId, $actor);
// Ledger: still +1

// 5a. Inspected — sellable (Returned → InStock, ledger +1)
$serialService->restock(new SerialNumber('SN-ABC-001'), $tenantId, $returnId, 4999, $actor);
// Ledger: +2

// 5b. OR — Inspected — damaged. Write off.
//     Coming from Returned (not InStock) → no ledger change.
$serialService->writeOff(new SerialNumber('SN-ABC-001'), $tenantId, 'Cracked screen', $actor, $returnId);

// 6. Consistency check (scheduled job)
$ok = $serialService->isConsistentWithLedger($variantId);
// true when ledger quantity === count of InStock serials
```

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| State machine in the aggregate, ledger writes in the service | The aggregate owns transition rules; the service owns side effects. Neither bleeds into the other's concern. |
| `requiresLedgerEntry()` on the status enum | Centralizes the logic for which transitions change sellability rather than scattering it across service if-statements |
| No ledger entry on `acceptReturn()` | A returned item is not sellable yet — adding it to the ledger on return would create phantom stock before inspection completes |
| `isConsistentWithLedger()` method | Drift between the two systems is detectable; run this as a scheduled job and alert on discrepancies |
| `Pending` as initial status | Pre-registering serials from a PO manifest enables scan-to-receive workflows where staff scan each item as they unload |
| Transition history in a child table | History is append-only and grows indefinitely — a child table prevents the main serials table from bloating and keeps queries fast |
| Per-tenant uniqueness on serial number | Global uniqueness is unenforceable across manufacturers; scoping to tenant prevents multi-tenancy data leakage |
