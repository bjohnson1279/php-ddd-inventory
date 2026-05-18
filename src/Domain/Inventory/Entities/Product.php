<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\ValueObjects\TransactionType;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;
use DateTimeImmutable;

class Product
{
    private string $id;
    private SKU $sku;
    private string $name;
    private Department $department;
    private Quantity $reorderThreshold;
    
    /** @var array<string, LocationStock> */
    private array $locationStocks = [];
    
    /** @var InventoryTransaction[] */
    private array $pendingTransactions = [];

    public function __construct(
        string $id, 
        SKU $sku, 
        string $name, 
        Department $department, 
        ?Quantity $reorderThreshold = null
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->name = $name;
        $this->department = $department;
        $this->reorderThreshold = $reorderThreshold ?? new Quantity(10);
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
            uniqid('txn_', true),
            $this->id,
            $type,
            $quantityChange,
            $condition,
            new DateTimeImmutable(),
            $reference
        );
    }

    public function receiveStockAt(LocationId $locationId, Quantity $quantity, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->addStock($quantity, new Condition(Condition::NEW));
        
        $this->recordTransaction(new TransactionType(TransactionType::RECEIPT), $quantity->getValue(), new Condition(Condition::NEW), $reference);
    }

    public function dispatchStockAt(LocationId $locationId, Quantity $quantity, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->subtractStock($this->sku->getValue(), $quantity, new Condition(Condition::NEW));
        
        $this->recordTransaction(new TransactionType(TransactionType::DISPATCH), -$quantity->getValue(), new Condition(Condition::NEW), $reference);
    }

    public function processSaleAt(LocationId $locationId, Quantity $quantity, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->subtractStock($this->sku->getValue(), $quantity, new Condition(Condition::NEW));
        
        $this->recordTransaction(new TransactionType(TransactionType::SALE), -$quantity->getValue(), new Condition(Condition::NEW), $reference);
        
        if ($this->isLowStock()) {
            // Low stock event
        }
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
    }

    public function processReturnAt(LocationId $locationId, Quantity $quantity, Condition $condition, ?string $reference = null): void
    {
        $stock = $this->getOrCreateLocationStock($locationId);
        $stock->addStock($quantity, $condition);
        
        $this->recordTransaction(new TransactionType(TransactionType::RETURN), $quantity->getValue(), $condition, $reference);
    }

    public function transferStock(LocationId $from, LocationId $to, Quantity $quantity, ?string $reference = null): void
    {
        $this->dispatchStockAt($from, $quantity, $reference ? "TRANSFER_OUT_$reference" : "TRANSFER_OUT");
        $this->receiveStockAt($to, $quantity, $reference ? "TRANSFER_IN_$reference" : "TRANSFER_IN");
    }
}
