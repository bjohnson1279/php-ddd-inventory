<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Infrastructure\Models\ReorderPolicyModel;

class EloquentReorderPolicyRepository implements ReorderPolicyRepositoryInterface
{
    private function mapToDomain(ReorderPolicyModel $model): ReorderPolicy
    {
        return new ReorderPolicy(
            $model->id,
            new SKU($model->sku),
            $model->location_id,
            $model->reorder_point,
            $model->reorder_quantity,
            $model->safety_stock
        );
    }

    public function findBySkuAndLocation(SKU $sku, string $locationId): ?ReorderPolicy
    {
        $model = ReorderPolicyModel::where('sku', $sku->getValue())
            ->where('location_id', $locationId)
            ->first();
        if (!$model) {
            return null;
        }
        return $this->mapToDomain($model);
    }

    public function save(ReorderPolicy $policy): void
    {
        ReorderPolicyModel::updateOrCreate(
            [
                'sku'         => $policy->sku->getValue(),
                'location_id' => $policy->locationId
            ],
            [
                'id'               => $policy->id,
                'reorder_point'    => $policy->reorderPoint,
                'reorder_quantity' => $policy->reorderQuantity,
                'safety_stock'     => $policy->safetyStock
            ]
        );
    }

    public function findAll(): array
    {
        $models = ReorderPolicyModel::all();
        $results = [];
        foreach ($models as $model) {
            $results[] = $this->mapToDomain($model);
        }
        return $results;
    }
}
