<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\InventoryCount;
// use App\Models\InventoryCountModel; // Assuming an Eloquent model exists

use InventoryApp\Infrastructure\Models\InventoryCountModel;
use InventoryApp\Infrastructure\Models\InventoryCountItemModel;
use Illuminate\Support\Facades\DB;

class EloquentInventoryCountRepository implements InventoryCountRepositoryInterface
{
    public function findById(string $id): ?InventoryCount
    {
        $model = InventoryCountModel::with('items')->find($id);
        if (!$model) return null;

        $items = [];
        foreach ($model->items as $itemModel) {
            $items[] = new \InventoryApp\Domain\Inventory\Entities\InventoryCountItem(
                new \InventoryApp\Domain\Inventory\ValueObjects\SKU($itemModel->sku),
                new \InventoryApp\Domain\Inventory\ValueObjects\Quantity($itemModel->counted_quantity)
            );
        }

        return new InventoryCount(
            $model->id,
            \InventoryApp\Domain\Inventory\ValueObjects\CountStatus::from($model->status),
            $items
        );
    }
    
    public function save(InventoryCount $inventoryCount): void
    {
        DB::transaction(function () use ($inventoryCount) {
            $model = InventoryCountModel::updateOrCreate(
                ['id' => $inventoryCount->getId()],
                ['status' => $inventoryCount->getStatus()->getValue(), 'created_at' => date('Y-m-d H:i:s')]
            );
            
            // Sync items
            foreach ($inventoryCount->getItems() as $item) {
                InventoryCountItemModel::updateOrCreate(
                    ['inventory_count_id' => $model->id, 'sku' => $item->getSku()->getValue()],
                    ['product_id' => null, 'counted_quantity' => $item->getCountedQuantity()->getValue(), 'created_at' => date('Y-m-d H:i:s')]
                );
            }
        });
    }
}
