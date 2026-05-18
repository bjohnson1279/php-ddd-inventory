<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\CountStatus;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Exception;

class InventoryCount
{
    private string $id;
    private CountStatus $status;
    /** @var array<string, InventoryCountItem> */
    private array $items = [];

    public function __construct(string $id, CountStatus $status, array $items = [])
    {
        $this->id = $id;
        $this->status = $status;
        
        foreach ($items as $item) {
            if ($item instanceof InventoryCountItem) {
                $this->items[$item->getSku()->getValue()] = $item;
            }
        }
    }

    public static function start(string $id): self
    {
        return new self($id, CountStatus::started());
    }

    public function recordCount(SKU $sku, Quantity $quantity): void
    {
        if ($this->status->isCompleted()) {
            throw new Exception("Cannot modify an already completed inventory count.");
        }

        // Add or overwrite the existing counted quantity for this SKU
        $this->items[$sku->getValue()] = new InventoryCountItem($sku, $quantity);
    }

    public function complete(): void
    {
        if ($this->status->isCompleted()) {
            throw new Exception("Inventory count is already completed.");
        }
        
        $this->status = CountStatus::completed();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): CountStatus
    {
        return $this->status;
    }

    /**
     * @return InventoryCountItem[]
     */
    public function getItems(): array
    {
        return array_values($this->items);
    }
}
