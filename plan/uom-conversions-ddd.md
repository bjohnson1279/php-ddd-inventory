# Unit of Measure Conversions

## The Core Problem

UOM conversion touches nearly every inventory flow:
- You **buy** a case of 24 → **receive** 24 units in the ledger
- You **sell** a half-dozen → **deduct** 6 units from the ledger
- You **alert** at 2 cases remaining → threshold is 48 units in the ledger
- You **report** stock as 144 units → display as 6 cases to the merchant

The fundamental rule: **the ledger always stores in the base unit.** Everything
else — receiving, selling, alerting, reporting — converts at the boundary.

There are two categories of units with different conversion strategies:

- **Discrete** (countable) — Each, Case, Dozen, Pack, Pallet. Conversion factors
  are product-specific (a "case" of soup ≠ a "case" of paper towels). Merchants
  configure these.
- **Continuous** (weight/volume) — g, kg, oz, lb, ml, l, fl oz. Conversion factors
  are fixed global constants. No configuration needed.

---

## Directory Structure

```
app/
└── Domain/
    └── Uom/
        ├── Aggregates/
        │   └── ProductUomConfiguration.php
        ├── Entities/
        │   └── ConversionRule.php
        ├── Enums/
        │   └── UomCategory.php
        ├── ValueObjects/
        │   ├── UnitOfMeasure.php
        │   └── Quantity.php
        ├── Services/
        │   ├── UomConverter.php
        │   └── StandardUnits.php
        └── Exceptions/
            └── IncompatibleUnitsException.php
```

---

## 1. UomCategory Enum

```php
// Domain/Uom/Enums/UomCategory.php

enum UomCategory: string
{
    case Discrete = 'discrete'; // countable — each, case, pack, dozen, pallet
    case Weight   = 'weight';   // g, kg, oz, lb
    case Volume   = 'volume';   // ml, l, fl_oz, cup, gallon
}
```

---

## 2. UnitOfMeasure Value Object

```php
// Domain/Uom/ValueObjects/UnitOfMeasure.php
//
// Describes a unit — what category it belongs to and how to display it.
// Does NOT contain conversion logic; that lives in ConversionRule and UomConverter.

final class UnitOfMeasure
{
    public function __construct(
        public readonly string $name,         // e.g. "Case"
        public readonly string $abbreviation, // e.g. "cs"
        public readonly UomCategory $category,
    ) {
        if (empty(trim($name)) || empty(trim($abbreviation))) {
            throw new \InvalidArgumentException('UnitOfMeasure name and abbreviation must be non-empty.');
        }
    }

    public function equals(UnitOfMeasure $other): bool
    {
        // Two units are the same if they share a name and category.
        // Abbreviation differences don't create distinct units.
        return $this->name === $other->name
            && $this->category === $other->category;
    }

    public function isCompatibleWith(UnitOfMeasure $other): bool
    {
        return $this->category === $other->category;
    }

    public function __toString(): string
    {
        return "{$this->name} ({$this->abbreviation})";
    }
}
```

---

## 3. Quantity Value Object

```php
// Domain/Uom/ValueObjects/Quantity.php
//
// A quantity is always an amount paired with a unit.
// Never store a bare number in the domain — it's always "24 cases" or "2.5 kg."

final class Quantity
{
    public function __construct(
        public readonly float $amount,
        public readonly UnitOfMeasure $unit,
    ) {
        if ($this->amount < 0) {
            throw new \InvalidArgumentException('Quantity amount cannot be negative.');
        }
    }

    /**
     * Add two quantities of the SAME unit.
     * If you need to add across units, convert first via UomConverter.
     */
    public function add(Quantity $other): self
    {
        $this->assertSameUnit($other);
        return new self($this->amount + $other->amount, $this->unit);
    }

    public function subtract(Quantity $other): self
    {
        $this->assertSameUnit($other);
        $result = $this->amount - $other->amount;
        if ($result < 0) {
            throw new \DomainException('Resulting quantity would be negative.');
        }
        return new self($result, $this->unit);
    }

    public function multiplyBy(int|float $factor): self
    {
        return new self($this->amount * $factor, $this->unit);
    }

    /**
     * For ledger writes: discrete items must be whole numbers.
     * Continuous items (weight/volume) are stored as integer smallest-units
     * (grams, milliliters) to avoid float precision issues.
     */
    public function toBaseInteger(): int
    {
        if ($this->unit->category !== UomCategory::Discrete) {
            throw new \DomainException(
                'Use toBaseInteger() only for discrete quantities. '
                . 'Continuous quantities should be converted to their smallest unit (g, ml) first.'
            );
        }

        if (fmod($this->amount, 1.0) !== 0.0) {
            throw new \DomainException(
                "Discrete quantity must be a whole number; got {$this->amount} {$this->unit->abbreviation}."
            );
        }

        return (int) $this->amount;
    }

    public function __toString(): string
    {
        return "{$this->amount} {$this->unit->abbreviation}";
    }

    private function assertSameUnit(Quantity $other): void
    {
        if (!$this->unit->equals($other->unit)) {
            throw new IncompatibleUnitsException(
                "Cannot operate on {$this->unit->name} and {$other->unit->name} directly. Convert first."
            );
        }
    }
}
```

---

## 4. StandardUnits — Well-Known Unit Definitions

```php
// Domain/Uom/Services/StandardUnits.php
//
// A catalog of commonly used units, available as static factories.
// Discrete units are here too, but product-specific ones (Case, Pack)
// should be created inline or stored per-tenant.

final class StandardUnits
{
    // --- Discrete ---
    public static function each(): UnitOfMeasure
    {
        return new UnitOfMeasure('Each', 'ea', UomCategory::Discrete);
    }

    public static function dozen(): UnitOfMeasure
    {
        return new UnitOfMeasure('Dozen', 'dz', UomCategory::Discrete);
    }

    public static function case(int $unitsPerCase): UnitOfMeasure
    {
        // Note: case is product-specific, so it's better modeled as a
        // ConversionRule on the ProductUomConfiguration than as a generic unit.
        // This factory is just a convenience for common configuration.
        return new UnitOfMeasure('Case', 'cs', UomCategory::Discrete);
    }

    // --- Weight ---
    public static function gram(): UnitOfMeasure
    {
        return new UnitOfMeasure('Gram', 'g', UomCategory::Weight);
    }

    public static function kilogram(): UnitOfMeasure
    {
        return new UnitOfMeasure('Kilogram', 'kg', UomCategory::Weight);
    }

    public static function ounce(): UnitOfMeasure
    {
        return new UnitOfMeasure('Ounce', 'oz', UomCategory::Weight);
    }

    public static function pound(): UnitOfMeasure
    {
        return new UnitOfMeasure('Pound', 'lb', UomCategory::Weight);
    }

    // --- Volume ---
    public static function milliliter(): UnitOfMeasure
    {
        return new UnitOfMeasure('Milliliter', 'ml', UomCategory::Volume);
    }

    public static function liter(): UnitOfMeasure
    {
        return new UnitOfMeasure('Liter', 'l', UomCategory::Volume);
    }

    public static function fluidOunce(): UnitOfMeasure
    {
        return new UnitOfMeasure('Fluid Ounce', 'fl oz', UomCategory::Volume);
    }

    public static function gallon(): UnitOfMeasure
    {
        return new UnitOfMeasure('Gallon', 'gal', UomCategory::Volume);
    }

    // -------------------------------------------------------------------------
    // Standard conversion factors to base unit (gram for weight, ml for volume)
    // These never need merchant configuration.
    // -------------------------------------------------------------------------

    public static function weightFactorToGrams(UnitOfMeasure $unit): float
    {
        return match($unit->name) {
            'Gram'      => 1.0,
            'Kilogram'  => 1000.0,
            'Ounce'     => 28.3495,
            'Pound'     => 453.592,
            default     => throw new IncompatibleUnitsException(
                "Unknown weight unit: {$unit->name}"
            ),
        };
    }

    public static function volumeFactorToMilliliters(UnitOfMeasure $unit): float
    {
        return match($unit->name) {
            'Milliliter'  => 1.0,
            'Liter'       => 1000.0,
            'Fluid Ounce' => 29.5735,
            'Cup'         => 236.588,
            'Gallon'      => 3785.41,
            default       => throw new IncompatibleUnitsException(
                "Unknown volume unit: {$unit->name}"
            ),
        };
    }
}
```

---

## 5. ConversionRule Entity

```php
// Domain/Uom/Entities/ConversionRule.php
//
// Hub-and-spoke model: all conversions are expressed relative to the
// product's base unit. To convert Case → Dozen, you go:
//   Case → Each (base) → Dozen
// This prevents contradictory rules (Case=24 Each, but also Case=3 Dozen
// when Dozen=11 Each would be inconsistent).

final class ConversionRule
{
    public function __construct(
        public readonly ConversionRuleId $id,
        public readonly UnitOfMeasure $unit,      // the non-base unit (e.g. Case)
        public readonly float $factorToBase,      // 1 Case = 24 Each → factorToBase = 24
        public readonly ?string $label = null,    // optional display label: "Case of 24"
    ) {
        if ($factorToBase <= 0) {
            throw new \InvalidArgumentException(
                "Conversion factor must be positive; got {$factorToBase}."
            );
        }

        if ($factorToBase === 1.0) {
            throw new \InvalidArgumentException(
                'A conversion factor of 1.0 would duplicate the base unit. '
                . 'Only add rules for units that differ from the base.'
            );
        }
    }

    /**
     * The inverse: how many of THIS unit equals one base unit.
     * e.g. if 1 Case = 24 Each, then factorFromBase = 1/24
     */
    public function factorFromBase(): float
    {
        return 1.0 / $this->factorToBase;
    }
}
```

---

## 6. ProductUomConfiguration Aggregate

```php
// Domain/Uom/Aggregates/ProductUomConfiguration.php
//
// Owns all UOM settings for a single product variant.
// All inventory ledger entries for this variant are in the base unit.
// The purchase unit and sale unit are used to convert at boundaries.

class ProductUomConfiguration
{
    private UnitOfMeasure $baseUnit;
    private ?UnitOfMeasure $purchaseUnit = null;
    private ?UnitOfMeasure $saleUnit     = null;

    /** @var ConversionRule[] */
    private array $conversionRules = [];

    public function __construct(
        public readonly ProductUomConfigurationId $id,
        public readonly ProductVariantId $variantId,
        UnitOfMeasure $baseUnit,
    ) {
        $this->baseUnit = $baseUnit;
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Declare that 1 $unit = $factorToBase base units for this product.
     * e.g. addConversionRule(Case, 24) means 1 Case = 24 Each.
     */
    public function addConversionRule(
        UnitOfMeasure $unit,
        float $factorToBase,
        ?string $label = null,
    ): void {
        if (!$unit->isCompatibleWith($this->baseUnit)) {
            throw new IncompatibleUnitsException(
                "Cannot convert between {$unit->category->value} and "
                . "{$this->baseUnit->category->value} units."
            );
        }

        if ($unit->equals($this->baseUnit)) {
            throw new \InvalidArgumentException(
                'Cannot add a conversion rule for the base unit itself.'
            );
        }

        // Prevent duplicate rules for the same unit
        foreach ($this->conversionRules as $existing) {
            if ($existing->unit->equals($unit)) {
                throw new \DomainException(
                    "A conversion rule for {$unit->name} already exists. Remove it first."
                );
            }
        }

        $this->conversionRules[] = new ConversionRule(
            id: ConversionRuleId::generate(),
            unit: $unit,
            factorToBase: $factorToBase,
            label: $label,
        );
    }

    public function removeConversionRule(UnitOfMeasure $unit): void
    {
        $this->conversionRules = array_values(array_filter(
            $this->conversionRules,
            fn(ConversionRule $r) => !$r->unit->equals($unit),
        ));
    }

    public function setPurchaseUnit(UnitOfMeasure $unit): void
    {
        $this->assertUnitIsKnown($unit);
        $this->purchaseUnit = $unit;
    }

    public function setSaleUnit(UnitOfMeasure $unit): void
    {
        $this->assertUnitIsKnown($unit);
        $this->saleUnit = $unit;
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function baseUnit(): UnitOfMeasure
    {
        return $this->baseUnit;
    }

    public function purchaseUnit(): UnitOfMeasure
    {
        return $this->purchaseUnit ?? $this->baseUnit;
    }

    public function saleUnit(): UnitOfMeasure
    {
        return $this->saleUnit ?? $this->baseUnit;
    }

    /**
     * Returns the factor needed to convert 1 of $unit to the base unit.
     * For standard weight/volume units, uses built-in constants.
     * For discrete units, uses configured ConversionRules.
     */
    public function factorToBase(UnitOfMeasure $unit): float
    {
        if ($unit->equals($this->baseUnit)) {
            return 1.0;
        }

        // Standard weight conversions — no configuration needed
        if ($unit->category === UomCategory::Weight) {
            $unitFactor = StandardUnits::weightFactorToGrams($unit);
            $baseFactor = StandardUnits::weightFactorToGrams($this->baseUnit);
            return $unitFactor / $baseFactor;
        }

        // Standard volume conversions — no configuration needed
        if ($unit->category === UomCategory::Volume) {
            $unitFactor = StandardUnits::volumeFactorToMilliliters($unit);
            $baseFactor = StandardUnits::volumeFactorToMilliliters($this->baseUnit);
            return $unitFactor / $baseFactor;
        }

        // Discrete: must be in a configured ConversionRule
        foreach ($this->conversionRules as $rule) {
            if ($rule->unit->equals($unit)) {
                return $rule->factorToBase;
            }
        }

        throw new IncompatibleUnitsException(
            "No conversion rule found for {$unit->name} → {$this->baseUnit->name}. "
            . 'Add a ConversionRule for this product.'
        );
    }

    /** @return ConversionRule[] */
    public function conversionRules(): array
    {
        return $this->conversionRules;
    }

    // -------------------------------------------------------------------------
    // Private guard
    // -------------------------------------------------------------------------

    private function assertUnitIsKnown(UnitOfMeasure $unit): void
    {
        if ($unit->equals($this->baseUnit)) {
            return; // base unit is always valid
        }

        foreach ($this->conversionRules as $rule) {
            if ($rule->unit->equals($unit)) {
                return;
            }
        }

        throw new \DomainException(
            "Unit {$unit->name} has no conversion rule defined. "
            . "Add it via addConversionRule() before using it as a purchase or sale unit."
        );
    }
}
```

---

## 7. UomConverter — Domain Service

```php
// Domain/Uom/Services/UomConverter.php
//
// Converts quantities between units using a ProductUomConfiguration.
// Hub-and-spoke: from → base → to.

class UomConverter
{
    /**
     * Convert a quantity from one unit to another.
     * Both units must be compatible (same category) and known to the config.
     */
    public function convert(
        Quantity $from,
        UnitOfMeasure $toUnit,
        ProductUomConfiguration $config,
    ): Quantity {
        if ($from->unit->equals($toUnit)) {
            return $from;
        }

        if (!$from->unit->isCompatibleWith($toUnit)) {
            throw new IncompatibleUnitsException(
                "Cannot convert {$from->unit->category->value} to {$toUnit->category->value}."
            );
        }

        // Step 1: convert to base unit
        $inBase = $from->amount * $config->factorToBase($from->unit);

        // Step 2: convert base to target unit
        $targetFactor = $config->factorToBase($toUnit);
        $converted    = $inBase / $targetFactor;

        return new Quantity($converted, $toUnit);
    }

    /**
     * Convenience: convert directly to the product's base unit.
     * This is what you call before writing to the ledger.
     */
    public function toBaseUnit(Quantity $quantity, ProductUomConfiguration $config): Quantity
    {
        return $this->convert($quantity, $config->baseUnit(), $config);
    }

    /**
     * Convert a unit cost from one unit to another.
     * e.g. $14.40 per case → $0.60 per each
     *
     * @param int $costCentsPerUnit  Cost in cents for 1 unit of $perUnit
     * @return int                   Cost in cents per 1 unit of $targetUnit
     */
    public function convertCost(
        int $costCentsPerUnit,
        UnitOfMeasure $perUnit,
        UnitOfMeasure $targetUnit,
        ProductUomConfiguration $config,
    ): int {
        if ($perUnit->equals($targetUnit)) {
            return $costCentsPerUnit;
        }

        // Factor: how many $targetUnits equal 1 $perUnit
        $factorPerToBase   = $config->factorToBase($perUnit);
        $factorTargetToBase = $config->factorToBase($targetUnit);

        // Cost per target unit = (cost per source unit) / (source units per target unit)
        $ratio = $factorPerToBase / $factorTargetToBase;

        return (int) round($costCentsPerUnit / $ratio);
    }
}
```

---

## 8. Integration with Inventory Flows

```php
// Domain/Inventory/Services/InventoryService.php (additions)
//
// The converter is injected and called at every boundary where a quantity
// enters or exits the ledger in a non-base unit.

class InventoryService
{
    public function __construct(
        private readonly LedgerRepositoryInterface $ledger,
        private readonly UomConverter $uomConverter,
        private readonly EventDispatcherInterface $events,
    ) {}

    /**
     * Receive stock from a purchase order.
     * The PO line may specify a quantity in a purchase unit (e.g. 5 Cases).
     * The ledger records in the base unit (e.g. 120 Each).
     */
    public function receiveStock(
        ProductVariantId $variantId,
        Quantity $receivedQty,              // e.g. Quantity(5, Case)
        int $costCentsPerReceivedUnit,       // e.g. 1440 cents = $14.40/case
        ProductUomConfiguration $uomConfig,
        string $purchaseOrderId,
        ActorId $actor,
    ): void {
        $baseQty     = $this->uomConverter->toBaseUnit($receivedQty, $uomConfig);
        $baseCostCents = $this->uomConverter->convertCost(
            costCentsPerUnit: $costCentsPerReceivedUnit,
            perUnit: $receivedQty->unit,
            targetUnit: $uomConfig->baseUnit(),
            config: $uomConfig,
        );

        $this->ledger->append(new LedgerEntry(
            id: LedgerEntryId::generate(),
            variantId: $variantId,
            quantity: $baseQty->toBaseInteger(),  // +120
            reason: ReasonCode::PurchaseReceipt,
            actor: $actor,
            referenceId: $purchaseOrderId,
            occurredAt: new \DateTimeImmutable(),
            metadata: [
                'receivedQty'        => $receivedQty->amount,
                'receivedUnit'       => $receivedQty->unit->abbreviation,
                'baseQty'            => $baseQty->amount,
                'baseUnit'           => $baseQty->unit->abbreviation,
                'unitCostCents'      => $baseCostCents,
            ],
        ));
    }

    /**
     * Sell stock at POS.
     * The sale may be in a sale unit (e.g. 1 Dozen).
     * The ledger deducts in the base unit (e.g. 12 Each).
     */
    public function decrementForSale(
        ProductVariantId $variantId,
        Quantity $soldQty,                  // e.g. Quantity(1, Dozen)
        ProductUomConfiguration $uomConfig,
        string $saleId,
        ActorId $actor,
    ): void {
        $baseQty = $this->uomConverter->toBaseUnit($soldQty, $uomConfig);
        $baseInt = $baseQty->toBaseInteger(); // 12

        $available = $this->ledger->currentQuantity($variantId);
        if ($available < $baseInt) {
            throw new InsufficientInventoryException($variantId, $available, $baseInt);
        }

        $this->ledger->append(new LedgerEntry(
            id: LedgerEntryId::generate(),
            variantId: $variantId,
            quantity: -$baseInt,
            reason: ReasonCode::Sale,
            actor: $actor,
            referenceId: $saleId,
            occurredAt: new \DateTimeImmutable(),
            metadata: [
                'soldQty'   => $soldQty->amount,
                'soldUnit'  => $soldQty->unit->abbreviation,
                'baseQty'   => $baseInt,
                'baseUnit'  => $uomConfig->baseUnit()->abbreviation,
            ],
        ));
    }
}
```

---

## 9. Low-Stock Alerts with UOM Thresholds

```php
// Domain/Inventory/ValueObjects/StockThreshold.php
//
// Thresholds are configured in a merchant-friendly unit (e.g. "alert at 2 Cases")
// but evaluated against the ledger's base-unit quantity.

final class StockThreshold
{
    public function __construct(
        public readonly Quantity $threshold,  // e.g. Quantity(2, Case)
    ) {}

    public function isBreached(int $currentBaseQty, ProductUomConfiguration $uomConfig, UomConverter $converter): bool
    {
        $thresholdInBase = $converter->toBaseUnit($this->threshold, $uomConfig);
        return $currentBaseQty <= $thresholdInBase->toBaseInteger();
    }

    public function __toString(): string
    {
        return (string) $this->threshold;
    }
}
```

---

## 10. Full Usage Example

```php
// --- Configuration ---

$config = new ProductUomConfiguration(
    id: ProductUomConfigurationId::generate(),
    variantId: $variantMediumBlue->id,
    baseUnit: StandardUnits::each(),
);

$config->addConversionRule(
    unit: new UnitOfMeasure('Case', 'cs', UomCategory::Discrete),
    factorToBase: 24,
    label: 'Case of 24',
);

$config->addConversionRule(
    unit: StandardUnits::dozen(),
    factorToBase: 12,
);

$config->setPurchaseUnit(new UnitOfMeasure('Case', 'cs', UomCategory::Discrete));
$config->setSaleUnit(StandardUnits::each());

// --- Receiving 5 cases at $14.40/case ---

$inventoryService->receiveStock(
    variantId: $variantMediumBlue->id,
    receivedQty: new Quantity(5, new UnitOfMeasure('Case', 'cs', UomCategory::Discrete)),
    costCentsPerReceivedUnit: 1440,
    uomConfig: $config,
    purchaseOrderId: $poId,
    actor: $warehouseActor,
);
// Ledger entry: +120 Each, unit cost = 60 cents/each

// --- Selling 1 dozen ---

$inventoryService->decrementForSale(
    variantId: $variantMediumBlue->id,
    soldQty: new Quantity(1, StandardUnits::dozen()),
    uomConfig: $config,
    saleId: $saleId,
    actor: $cashierActor,
);
// Ledger entry: -12 Each

// --- Checking a low-stock threshold (alert at 2 cases = 48 each) ---

$threshold = new StockThreshold(
    new Quantity(2, new UnitOfMeasure('Case', 'cs', UomCategory::Discrete))
);

$current = $ledger->currentQuantity($variantMediumBlue->id); // e.g. 36

if ($threshold->isBreached($current, $config, $uomConverter)) {
    // Fire LowStockAlert event — current: 36 each, threshold: 48 each (2 cases)
}

// --- Converting a quantity for display ---

$currentQty    = new Quantity($current, StandardUnits::each());
$displayInCases = $uomConverter->convert(
    $currentQty,
    new UnitOfMeasure('Case', 'cs', UomCategory::Discrete),
    $config,
);
// → Quantity(1.5, Case) — "1.5 cases remaining"
```

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| Hub-and-spoke conversions (all relative to base) | Prevents contradictory rules; any A→B conversion goes through base, so consistency is guaranteed |
| Ledger always stores in base units | Reports, alerts, and counts all work from one consistent number — no per-query conversion needed |
| Standard weight/volume factors built-in | Merchants never need to configure that 1 kg = 1000 g; only discrete units need configuration |
| Cost conversion alongside quantity | Receiving at case price must correctly calculate per-unit cost for inventory valuation |
| `StockThreshold` in merchant-friendly units | Merchants think in cases, not eaches; storing the threshold in their unit avoids confusing config UIs |
| Metadata on ledger entries | Preserves the original received/sold unit for audit, reporting, and UI display without polluting the base-unit quantity |
| `toBaseInteger()` guard on discrete quantities | Ensures you never write 1.5 eaches to the ledger — catches misconfigured conversion factors at the domain layer |
