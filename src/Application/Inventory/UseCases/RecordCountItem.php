<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Exception;

class RecordCountItem
{
    private InventoryCountRepositoryInterface $countRepository;

    public function __construct(InventoryCountRepositoryInterface $countRepository)
    {
        $this->countRepository = $countRepository;
    }

    public function execute(string $countId, string $skuValue, int $quantityValue): void
    {
        $inventoryCount = $this->countRepository->findById($countId);

        if (!$inventoryCount) {
            throw new Exception("Inventory count not found: " . $countId);
        }

        $sku = new SKU($skuValue);
        $quantity = new Quantity($quantityValue);

        // Record the counted item in the aggregate
        $inventoryCount->recordCount($sku, $quantity);

        $this->countRepository->save($inventoryCount);
    }
}
