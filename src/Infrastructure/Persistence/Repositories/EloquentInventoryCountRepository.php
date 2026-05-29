<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\InventoryCount;
use InventoryApp\Domain\Inventory\Entities\InventoryCountItem;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\CountStatus;
use InventoryApp\Infrastructure\Models\InventoryCountModel;
use InventoryApp\Infrastructure\Models\InventoryCountItemModel;
use Illuminate\Database\Capsule\Manager as Capsule;

class EloquentInventoryCountRepository implements InventoryCountRepositoryInterface
{
    public function __construct(private readonly string $tenantId) {}

    public function findById(string $id): ?InventoryCount
    {
        $model = InventoryCountModel::with('items')
            ->where('tenant_id', $this->tenantId)
            ->find($id);

        if (!$model) return null;

        $items = [];
        foreach ($model->items as $itemModel) {
            $items[] = new InventoryCountItem(
                new SKU($itemModel->sku),
                new Quantity($itemModel->counted_quantity)
            );
        }

        return new InventoryCount(
            $model->id,
            new CountStatus($model->status),
            $items
        );
    }

    public function save(InventoryCount $inventoryCount): void
    {
        Capsule::connection()->transaction(function () use ($inventoryCount) {
            $model = InventoryCountModel::updateOrCreate(
                ['id' => $inventoryCount->getId()],
                [
                    'tenant_id'  => $this->tenantId,
                    'status'     => $inventoryCount->getStatus()->getValue(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            foreach ($inventoryCount->getItems() as $item) {
                InventoryCountItemModel::updateOrCreate(
                    [
                        'inventory_count_id' => $model->id,
                        'sku'                => $item->getSku()->getValue(),
                    ],
                    [
                        'product_id'       => null,
                        'counted_quantity'  => $item->getCountedQuantity()->getValue(),
                        'created_at'        => date('Y-m-d H:i:s'),
                    ]
                );
            }
        });
    }
}
