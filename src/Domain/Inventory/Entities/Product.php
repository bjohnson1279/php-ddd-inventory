<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Shared\Entities\AggregateRoot;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\ValueObjects\TransactionType;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\StockDispatched;
use InventoryApp\Domain\Inventory\Events\SaleProcessed;
use InventoryApp\Domain\Inventory\Events\ReturnProcessed;
use InventoryApp\Domain\Inventory\Events\StockReconciled;
use InventoryApp\Domain\Inventory\Events\StockTransferred;
use InventoryApp\Domain\Inventory\Events\LowStockDetected;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class Product extends AggregateRoot
{
    private string $id;
    private SKU $sku;
    private string $name;
    private Department $department;
    private Quantity $reorderThreshold;
    private int $versionId;
    private ?int $weightGrams;
    private ?float $volumeCubicMeters;
    
    /** @var array<string, LocationStock> */
    private array $locationStocks = [];
    
    /** @var InventoryTransaction[] */
    private array $pendingTransactions = [];

    public function __construct(
        string $id, 
        SKU $sku, 
        string $name, 
        Department $department, 
        ?Quantity $reorderThreshold = null,
        int $versionId = 1,
        ?int $weightGrams = null,
        ?float $volumeCubicMeters = null
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->name = $name;
        $this->department = $department;
        $this->reorderThreshold = $reorderThreshold ?? new Quantity(10);
        $this->versionId = $versionId;
        $this->weightGrams = $weightGrams;
        $this->volumeCubicMeters = $volumeCubicMeters;
    }

    public static function create(
        string $id, 
        SKU $sku, 
        string $name, 
        Department $department, 
        LocationId $initialLocation,
        Quantity $initialStock,
        ?Quantity $reorderThreshold = null
    ): self {
        $product = new self($id, $sku, $name, $department, $reorderThreshold);
        if ($initialStock->getValue() > 0) {
            $product->receiveStockAt($initialLocation, $initialStock, 'INITIAL_STOCK');
        }
        return $product;
    }

    public function getId(): string { return $this->id; }
    public function getSku(): SKU { return $this->sku; }
    public function getName(): string { return $this->name; }
    public function getDepartment(): Department { return $this->department; }
    public function getReorderThreshold(): Quantity { return $this->reorderThreshold; }
    public function getVersionId(): int { return $this->versionId; }
    public function getWeightGrams(): ?int { return $this->weightGrams; }
    public function getVolumeCubicMeters(): ?float { return $this->volumeCubicMeters; }
    public function incrementVersion(): void { $this->versionId++; }

    public function loadLocationStock(LocationStock $stock): void
    {
        $this->locationStocks[$stock->getLocationId()->getValue()] = $stock;
    }

    /**
     * @return array<string, LocationStock>
     */
    public function getLocationStocks(): array
    {
        return $this->locationStocks;
    }

    public function getStockAt(LocationId $locationId): LocationStock
    {
        return $this->getOrCreateLocationStock($locationId);
    }

    private function getOrCreateLocationStock(LocationId $locationId): LocationStock
    {
        $idStr = $locationId->getValue();
        if (!isset($this->locationStocks[$idStr])) {
            $this->locationStocks[$idStr] = new LocationStock($locationId, new Quantity(0));
        }
        return $this->locationStocks[$idStr];
    }

    public function getTotalStockQuantity(): Quantity
    {
        $total = 0;
        foreach ($this->locationStocks as $stock) {
            $total += $stock->getStockQuantity()->getValue();
        }
        return new Quantity($total);
    }

    public function isLowStock(): bool
    {
        return $this->getTotalStockQuantity()->getValue() <= $this->reorderThreshold->getValue();
    }

    public function getPendingTransactions(): array
    {
        return $this->pendingTransactions;
    }

    public function clearPendingTransactions(): void
    {
        $this->pendingTransactions = [];
    }

    private function recordTransaction(
        TransactionType $type, 
        int $quantityChange, 
        Condition $condition, 
        ?string $reference = null
    ): void {
        $this->pendingTransactions[] = new InventoryTransaction(
            Uuid::uuid4()->toString(),
            $this->id,
            $type,
            $quantityChange,
            $condition,
            new DateTimeImmutable(),
            $reference
        );
    }

    // -----------------------------------------------------------------
    // Stock mutations — each fires a typed domain event
    // -----------------------------------------------------------------

    public function receiveStockAt(LocationId $locationId, Quantity $quantity, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->addStock($quantity, new Condition(Condition::NEW));
        
        $this->recordTransaction(
            new TransactionType(TransactionType::RECEIPT),
            $quantity->getValue(),
            new Condition(Condition::NEW),
            $reference
        );

        $this->recordEvent(new StockReceived(
            $this->sku,
            $locationId,
            $quantity->getValue(),
            $reference,
            new DateTimeImmutable(),
        ));
    }

    public function dispatchStockAt(LocationId $locationId, Quantity $quantity, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->subtractStock($this->sku->getValue(), $quantity, new Condition(Condition::NEW));
        
        $this->recordTransaction(
            new TransactionType(TransactionType::DISPATCH),
            -$quantity->getValue(),
            new Condition(Condition::NEW),
            $reference
        );

        $this->recordEvent(new StockDispatched(
            $this->sku,
            $locationId,
            $quantity->getValue(),
            $reference,
            new DateTimeImmutable(),
        ));

        $this->recordLowStockIfNeeded();
    }

    public function processSaleAt(LocationId $locationId, Quantity $quantity, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->subtractStock($this->sku->getValue(), $quantity, new Condition(Condition::NEW));
        
        $this->recordTransaction(
            new TransactionType(TransactionType::SALE),
            -$quantity->getValue(),
            new Condition(Condition::NEW),
            $reference
        );

        $this->recordEvent(new SaleProcessed(
            $this->sku,
            $locationId,
            $quantity->getValue(),
            $reference,
            new DateTimeImmutable(),
        ));

        $this->recordLowStockIfNeeded();
    }

    public function reconcileStockAt(LocationId $locationId, Quantity $actualQuantity, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $difference = $actualQuantity->getValue() - $stock->getStockQuantity()->getValue();
        
        if ($difference > 0) {
            $stock->addStock(new Quantity($difference), new Condition(Condition::NEW));
            $this->recordTransaction(new TransactionType(TransactionType::ADJUSTMENT), $difference, new Condition(Condition::NEW), $reference);
        } elseif ($difference < 0) {
            $stock->subtractStock($this->sku->getValue(), new Quantity(-$difference), new Condition(Condition::NEW));
            $this->recordTransaction(new TransactionType(TransactionType::ADJUSTMENT), $difference, new Condition(Condition::NEW), $reference);
        }

        if ($difference !== 0) {
            $this->recordEvent(new StockReconciled(
                $this->sku,
                $locationId,
                $actualQuantity->getValue(),
                $difference,
                $reference,
                new DateTimeImmutable(),
            ));

            if ($difference < 0) {
                $this->recordLowStockIfNeeded();
            }
        }
    }

    public function processReturnAt(LocationId $locationId, Quantity $quantity, Condition $condition, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->addStock($quantity, $condition);
        
        $this->recordTransaction(new TransactionType(TransactionType::RETURN), $quantity->getValue(), $condition, $reference);

        $this->recordEvent(new ReturnProcessed(
            $this->sku,
            $locationId,
            $quantity->getValue(),
            $condition,
            $reference,
            new DateTimeImmutable(),
        ));
    }

    public function transferStock(LocationId $from, LocationId $to, Quantity $quantity, ?string $reference = null): void
    {
        // Mutate stock directly without firing individual StockDispatched/StockReceived events,
        // then emit a single semantically-rich StockTransferred event instead.
        $fromStock = $this->getOrCreateLocationStock($from);
        $fromStock->subtractStock($this->sku->getValue(), $quantity, new Condition(Condition::NEW));
        $this->recordTransaction(
            new TransactionType(TransactionType::DISPATCH),
            -$quantity->getValue(),
            new Condition(Condition::NEW),
            $reference ? "TRANSFER_OUT_$reference" : "TRANSFER_OUT"
        );

        $toStock = $this->getOrCreateLocationStock($to);
        $toStock->addStock($quantity, new Condition(Condition::NEW));
        $this->recordTransaction(
            new TransactionType(TransactionType::RECEIPT),
            $quantity->getValue(),
            new Condition(Condition::NEW),
            $reference ? "TRANSFER_IN_$reference" : "TRANSFER_IN"
        );

        $this->recordEvent(new StockTransferred(
            $this->sku,
            $from,
            $to,
            $quantity->getValue(),
            $reference,
            new DateTimeImmutable(),
        ));

        $this->recordLowStockIfNeeded();
    }

    public function allocateStockAt(LocationId $locationId, Quantity $quantity): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->allocate($quantity, $this->sku->getValue());
        $this->incrementVersion();
    }

    public function releaseAllocationAt(LocationId $locationId, Quantity $quantity): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->releaseAllocation($quantity);
        $this->incrementVersion();
    }

    public function fulfillAllocationAt(LocationId $locationId, Quantity $quantity): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->fulfillAllocation($quantity);
        
        $this->recordTransaction(
            new TransactionType(TransactionType::DISPATCH),
            -$quantity->getValue(),
            new Condition(Condition::NEW),
            "FULFILL_ALLOCATION"
        );
        
        $this->recordEvent(new StockDispatched(
            $this->sku,
            $locationId,
            $quantity->getValue(),
            "FULFILL_ALLOCATION",
            new DateTimeImmutable()
        ));
        
        $this->incrementVersion();
        $this->recordLowStockIfNeeded();
    }

    public function createInTransitAt(LocationId $locationId, Quantity $quantity): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->createInTransit($quantity);
        $this->incrementVersion();
    }

    public function receiveInTransitAt(LocationId $locationId, Quantity $quantity): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->receiveInTransit($quantity);
        
        $this->recordTransaction(
            new TransactionType(TransactionType::RECEIPT),
            $quantity->getValue(),
            new Condition(Condition::NEW),
            "RECEIVE_IN_TRANSIT"
        );
        
        $this->recordEvent(new StockReceived(
            $this->sku,
            $locationId,
            $quantity->getValue(),
            "RECEIVE_IN_TRANSIT",
            new DateTimeImmutable()
        ));
        
        $this->incrementVersion();
    }

    public function cancelInTransitAt(LocationId $locationId, Quantity $quantity): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->cancelInTransit($quantity);
        $this->incrementVersion();
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function recordLowStockIfNeeded(): void
    {
        if ($this->isLowStock()) {
            $this->recordEvent(new LowStockDetected(
                $this->sku,
                $this->getTotalStockQuantity()->getValue(),
                $this->reorderThreshold->getValue(),
                new DateTimeImmutable(),
            ));
        }
    }
}
