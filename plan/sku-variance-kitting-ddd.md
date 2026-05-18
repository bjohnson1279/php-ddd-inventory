# SKU Variance & Kitting — DDD Modeling in Laravel

## Directory Structure

```
app/
└── Domain/
    ├── Product/
    │   ├── Aggregates/
    │   │   └── Product.php
    │   ├── Entities/
    │   │   └── ProductVariant.php
    │   └── ValueObjects/
    │       ├── SKU.php
    │       ├── VariantAttribute.php
    │       └── VariantAttributeSet.php
    ├── Kit/
    │   ├── Aggregates/
    │   │   └── Kit.php
    │   └── ValueObjects/
    │       └── KitComponent.php
    └── Inventory/
        ├── Aggregates/
        │   └── InventoryLedger.php
        ├── Entities/
        │   └── LedgerEntry.php
        ├── Enums/
        │   └── ReasonCode.php
        ├── Events/
        │   └── InventoryDecremented.php
        ├── Exceptions/
        │   └── InsufficientInventoryException.php
        ├── Services/
        │   └── InventoryService.php
        └── Repositories/
            └── LedgerRepositoryInterface.php
```

---

## 1. Value Objects

```php
// Domain/Product/ValueObjects/SKU.php
final class SKU
{
    public function __construct(public readonly string $value)
    {
        $trimmed = trim($value);
        if (empty($trimmed)) {
            throw new \InvalidArgumentException('SKU cannot be empty.');
        }
        // Enforce uppercase, alphanumeric + hyphens as a common convention
        if (!preg_match('/^[A-Z0-9\-]+$/', strtoupper($trimmed))) {
            throw new \InvalidArgumentException("Invalid SKU format: {$trimmed}");
        }
    }

    public function equals(SKU $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

```php
// Domain/Product/ValueObjects/VariantAttribute.php
//
// Represents a single dimension of variance — e.g., name="size", value="M"
// Kept as a value object: two attributes with the same name/value are identical.
final class VariantAttribute
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
        if (empty(trim($name)) || empty(trim($value))) {
            throw new \InvalidArgumentException('Attribute name and value must be non-empty.');
        }
    }

    public function equals(VariantAttribute $other): bool
    {
        return $this->name === $other->name && $this->value === $other->value;
    }
}
```

```php
// Domain/Product/ValueObjects/VariantAttributeSet.php
//
// An immutable set of VariantAttributes. Two variants with the same attribute
// set on the same product are a conflict — enforce uniqueness here.
final class VariantAttributeSet
{
    private array $attributes;

    public function __construct(VariantAttribute ...$attributes)
    {
        if (empty($attributes)) {
            throw new \InvalidArgumentException('A variant must have at least one attribute.');
        }

        // Sort by attribute name so {"size:M","color:red"} === {"color:red","size:M"}
        usort($attributes, fn($a, $b) => strcmp($a->name, $b->name));
        $this->attributes = $attributes;
    }

    public function equals(VariantAttributeSet $other): bool
    {
        if (count($this->attributes) !== count($other->attributes)) {
            return false;
        }
        foreach ($this->attributes as $i => $attr) {
            if (!$attr->equals($other->attributes[$i])) {
                return false;
            }
        }
        return true;
    }

    public function all(): array
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        return array_map(
            fn(VariantAttribute $a) => ['name' => $a->name, 'value' => $a->value],
            $this->attributes,
        );
    }
}
```

---

## 2. Product Aggregate + ProductVariant Entity

```php
// Domain/Product/Entities/ProductVariant.php
class ProductVariant
{
    public function __construct(
        public readonly ProductVariantId $id,
        public readonly ProductId $productId,
        public readonly SKU $sku,
        public readonly VariantAttributeSet $attributes,
    ) {}
}
```

```php
// Domain/Product/Aggregates/Product.php
//
// Product is the aggregate root. All variant creation/access
// flows through it to enforce the invariant: no two variants
// on the same product can share identical attribute combinations.
class Product
{
    /** @var ProductVariant[] */
    private array $variants = [];

    public function __construct(
        public readonly ProductId $id,
        public readonly string $name,
    ) {}

    public function addVariant(SKU $sku, VariantAttribute ...$attributes): ProductVariant
    {
        $attributeSet = new VariantAttributeSet(...$attributes);

        // Enforce uniqueness — no duplicate attribute combos per product
        foreach ($this->variants as $existing) {
            if ($existing->attributes->equals($attributeSet)) {
                throw new DuplicateVariantException(
                    "A variant with these attributes already exists on product {$this->id->value}."
                );
            }
        }

        $variant = new ProductVariant(
            id: ProductVariantId::generate(),
            productId: $this->id,
            sku: $sku,
            attributes: $attributeSet,
        );

        $this->variants[$variant->id->value] = $variant;

        return $variant;
    }

    public function findVariant(ProductVariantId $id): ?ProductVariant
    {
        return $this->variants[$id->value] ?? null;
    }

    /** @return ProductVariant[] */
    public function variants(): array
    {
        return array_values($this->variants);
    }
}
```

**Usage:**
```php
$tshirt = new Product(ProductId::generate(), 'Classic T-Shirt');

$variantSmallRed = $tshirt->addVariant(
    new SKU('TSHIRT-SM-RED'),
    new VariantAttribute('size', 'S'),
    new VariantAttribute('color', 'red'),
);

$variantMediumBlue = $tshirt->addVariant(
    new SKU('TSHIRT-MD-BLU'),
    new VariantAttribute('size', 'M'),
    new VariantAttribute('color', 'blue'),
);

// This would throw DuplicateVariantException:
$tshirt->addVariant(
    new SKU('TSHIRT-SM-RED-2'),
    new VariantAttribute('color', 'red'),
    new VariantAttribute('size', 'S'), // same combo, different order — still caught
);
```

---

## 3. Kit Aggregate

```php
// Domain/Kit/ValueObjects/KitComponent.php
//
// References a specific ProductVariant by ID. A kit works at the variant
// level — not the product level — so the correct inventory is always targeted.
final class KitComponent
{
    public function __construct(
        public readonly ProductVariantId $variantId,
        public readonly int $quantity,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Kit component quantity must be at least 1.');
        }
    }
}
```

```php
// Domain/Kit/Aggregates/Kit.php
class Kit
{
    /** @var KitComponent[] */
    private array $components = [];

    public function __construct(
        public readonly KitId $id,
        public readonly SKU $sku,
        public readonly string $name,
    ) {}

    public function addComponent(ProductVariantId $variantId, int $quantity): void
    {
        // If the same variant is added twice, merge quantities rather than
        // creating duplicate component entries.
        foreach ($this->components as $i => $component) {
            if ($component->variantId->equals($variantId)) {
                $this->components[$i] = new KitComponent($variantId, $component->quantity + $quantity);
                return;
            }
        }

        $this->components[] = new KitComponent($variantId, $quantity);
    }

    /** @return KitComponent[] */
    public function components(): array
    {
        return $this->components;
    }

    public function isEmpty(): bool
    {
        return empty($this->components);
    }
}
```

**Usage:**
```php
// "Starter Bundle" = 1 Medium Blue T-Shirt + 2 pairs of socks
$bundle = new Kit(KitId::generate(), new SKU('BUNDLE-STARTER'), 'Starter Bundle');

$bundle->addComponent($variantMediumBlue->id, quantity: 1);
$bundle->addComponent($socksWhiteMedium->id, quantity: 2);
```

---

## 4. Inventory Ledger (Append-Only)

```php
// Domain/Inventory/Enums/ReasonCode.php
enum ReasonCode: string
{
    case Sale            = 'sale';
    case KitSale         = 'kit_sale';       // Distinguishes kit-driven decrements
    case PurchaseReceipt = 'purchase_receipt';
    case Return          = 'return';
    case Damage          = 'damage';
    case Transfer        = 'transfer';
    case CountAdjustment = 'count_adjustment';
    case WriteOff        = 'write_off';
    case Shrinkage       = 'shrinkage';
}
```

```php
// Domain/Inventory/Entities/LedgerEntry.php
//
// Immutable by design. Quantity is signed: positive = stock in, negative = stock out.
// Never mutate entries — corrections are new entries with ReasonCode::CountAdjustment.
final class LedgerEntry
{
    public function __construct(
        public readonly LedgerEntryId $id,
        public readonly ProductVariantId $variantId,
        public readonly int $quantity,        // signed
        public readonly ReasonCode $reason,
        public readonly ActorId $actor,
        public readonly ?string $referenceId, // saleId, poId, kitId, etc.
        public readonly \DateTimeImmutable $occurredAt,
    ) {
        if ($quantity === 0) {
            throw new \InvalidArgumentException('A ledger entry quantity cannot be zero.');
        }
    }

    public function isDeduction(): bool
    {
        return $this->quantity < 0;
    }
}
```

```php
// Domain/Inventory/Repositories/LedgerRepositoryInterface.php
interface LedgerRepositoryInterface
{
    public function append(LedgerEntry $entry): void;

    // Current stock is a projection — the sum of all signed entries for a variant.
    public function currentQuantity(ProductVariantId $variantId): int;

    /** @return LedgerEntry[] */
    public function entriesFor(ProductVariantId $variantId): array;
}
```

---

## 5. InventoryService — The Critical Decrement Logic

```php
// Domain/Inventory/Services/InventoryService.php
//
// Domain service (not application service) — owns the inventory decrement rules.
// Injected with the ledger repo and event dispatcher via constructor.
class InventoryService
{
    public function __construct(
        private readonly LedgerRepositoryInterface $ledger,
        private readonly EventDispatcherInterface $events,
    ) {}

    // -----------------------------------------------------------------------
    // Single-variant sale
    // -----------------------------------------------------------------------

    public function decrementForSale(
        ProductVariantId $variantId,
        int $quantity,
        string $saleId,
        ActorId $actor,
    ): void {
        $this->assertSufficientStock($variantId, $quantity);

        $this->ledger->append(new LedgerEntry(
            id: LedgerEntryId::generate(),
            variantId: $variantId,
            quantity: -$quantity,
            reason: ReasonCode::Sale,
            actor: $actor,
            referenceId: $saleId,
            occurredAt: new \DateTimeImmutable(),
        ));

        $this->events->dispatch(new InventoryDecremented($variantId, $quantity, $saleId));
    }

    // -----------------------------------------------------------------------
    // Kit sale — the important part
    //
    // The core rule: validate ALL components before writing ANY ledger entries.
    // If component C of 5 is out of stock, you don't want entries 1-4 already
    // committed. This is not a database transaction concern — it's a domain
    // invariant that must be checked at this layer first.
    // -----------------------------------------------------------------------

    public function decrementForKitSale(
        Kit $kit,
        int $kitQuantity,
        string $saleId,
        ActorId $actor,
    ): void {
        if ($kit->isEmpty()) {
            throw new \DomainException('Cannot sell a kit with no components.');
        }

        // --- Pass 1: validate all components upfront ---
        foreach ($kit->components() as $component) {
            $needed = $component->quantity * $kitQuantity;
            $this->assertSufficientStock($component->variantId, $needed);
        }

        // --- Pass 2: write ledger entries for each component ---
        // All validations passed, so we commit atomically at the app layer
        // (wrap this entire service call in a DB transaction there).
        foreach ($kit->components() as $component) {
            $this->ledger->append(new LedgerEntry(
                id: LedgerEntryId::generate(),
                variantId: $component->variantId,
                quantity: -($component->quantity * $kitQuantity),
                reason: ReasonCode::KitSale,
                referenceId: $saleId,   // same saleId ties all entries together
                actor: $actor,
                occurredAt: new \DateTimeImmutable(),
            ));

            $this->events->dispatch(
                new InventoryDecremented($component->variantId, $component->quantity * $kitQuantity, $saleId)
            );
        }
    }

    // -----------------------------------------------------------------------
    // Private guard — reusable across all decrement operations
    // -----------------------------------------------------------------------

    private function assertSufficientStock(ProductVariantId $variantId, int $needed): void
    {
        $available = $this->ledger->currentQuantity($variantId);

        if ($available < $needed) {
            throw new InsufficientInventoryException(
                variantId: $variantId,
                available: $available,
                requested: $needed,
            );
        }
    }
}
```

---

## 6. Application Layer — Tying It Together

```php
// Application/Inventory/SellKitCommand.php + Handler
//
// The application layer handles the DB transaction and orchestration.
// The domain layer knows nothing about transactions.

class SellKitCommandHandler
{
    public function __construct(
        private readonly KitRepository $kits,
        private readonly InventoryService $inventoryService,
        private readonly \Illuminate\Database\ConnectionInterface $db,
    ) {}

    public function handle(SellKitCommand $command): void
    {
        $kit = $this->kits->findOrFail($command->kitId);

        // Wrap the domain service call in a DB transaction so that all
        // ledger entries are committed — or none are — at the persistence layer.
        $this->db->transaction(function () use ($kit, $command) {
            $this->inventoryService->decrementForKitSale(
                kit: $kit,
                kitQuantity: $command->quantity,
                saleId: $command->saleId,
                actor: ActorId::from($command->actorId),
            );
        });
    }
}
```

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| Variants referenced by ID in kits | Ensures the right variant's inventory is decremented, not an ambiguous base product |
| Two-pass validation in `decrementForKitSale` | Prevents partial commits at the domain level before the DB transaction ever runs |
| Signed quantities in ledger | A single append-only table handles all in/out flows; current stock is always a `SUM()` projection |
| `referenceId` on every entry | Ties all entries for a single sale/PO/transfer together for audit and reversal |
| `KitSale` reason code | Distinguishes kit-driven decrements from direct sales in analytics and audit queries |
| Duplicate attribute check in `Product` | Enforces the domain invariant that variant combos must be unique per product |
