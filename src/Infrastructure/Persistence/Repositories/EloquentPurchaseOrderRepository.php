<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Infrastructure\Models\PurchaseOrderModel;
use InventoryApp\Infrastructure\Models\PurchaseOrderItemModel;
use Illuminate\Database\Capsule\Manager as DB;

class EloquentPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    private function mapToDomain(PurchaseOrderModel $model): PurchaseOrder
    {
        $items = [];
        foreach ($model->items as $item) {
            $items[] = new PurchaseOrderItem(
                $item->id,
                $item->variant_id,
                $item->quantity,
                $item->unit_cost_cents,
                $item->received_quantity
            );
        }

        return new PurchaseOrder(
            $model->id,
            $model->purchase_order_number,
            $model->vendor_id,
            $model->tenant_id,
            $model->location_id,
            PurchaseOrderStatus::from($model->status),
            $items,
            $model->created_at,
            $model->updated_at
        );
    }

    public function findById(string $id): ?PurchaseOrder
    {
        $model = PurchaseOrderModel::with('items')->find($id);
        if (!$model) {
            return null;
        }
        return $this->mapToDomain($model);
    }

    public function findByNumber(string $poNumber): ?PurchaseOrder
    {
        $model = PurchaseOrderModel::with('items')
            ->where('purchase_order_number', $poNumber)
            ->first();
        if (!$model) {
            return null;
        }
        return $this->mapToDomain($model);
    }

    public function findAll(): array
    {
        $models = PurchaseOrderModel::with('items')->get();
        $results = [];
        foreach ($models as $model) {
            $results[] = $this->mapToDomain($model);
        }
        return $results;
    }

    public function save(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            PurchaseOrderModel::updateOrCreate(
                ['id' => $po->id],
                [
                    'purchase_order_number' => $po->purchaseOrderNumber,
                    'vendor_id'             => $po->vendorId,
                    'tenant_id'             => $po->tenantId,
                    'status'                => $po->getStatus()->value,
                    'location_id'           => $po->locationId,
                ]
            );

            // Deleting items not present in the aggregate (though usually PO items don't change, we can upsert all items)
            $itemIds = array_map(fn($item) => $item->id, $po->getItems());
            PurchaseOrderItemModel::where('purchase_order_id', $po->id)
                ->whereNotIn('id', $itemIds)
                ->delete();

            $itemData = [];
            foreach ($po->getItems() as $item) {
                $itemData[] = [
                    'id'                => $item->id,
                    'purchase_order_id' => $po->id,
                    'variant_id'        => $item->variantId,
                    'quantity'          => $item->quantity,
                    'received_quantity' => $item->getReceivedQuantity(),
                    'unit_cost_cents'   => $item->unitCostCents,
                ];
            }

            if (!empty($itemData)) {
                if ((new PurchaseOrderItemModel)->getConnection()->getDriverName() === 'sqlite') {
                    foreach ($itemData as $itemRow) {
                        PurchaseOrderItemModel::updateOrCreate(
                            ['id' => $itemRow['id']],
                            $itemRow
                        );
                    }
                } else {
                    PurchaseOrderItemModel::upsert(
                        $itemData,
                        ['id'],
                        ['purchase_order_id', 'variant_id', 'quantity', 'received_quantity', 'unit_cost_cents']
                    );
                }
            }
        });
    }
}
