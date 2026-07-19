<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Repositories\WarehouseLocationRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\WarehouseLocation;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Infrastructure\Models\WarehouseLocationModel;

class EloquentWarehouseLocationRepository implements WarehouseLocationRepositoryInterface
{
    public function save(WarehouseLocation $location): void
    {
        WarehouseLocationModel::updateOrCreate(
            ['id' => $location->getId()->getValue()],
            [
                'warehouse_id'            => $location->getWarehouseId(),
                'zone'                    => $location->getZone(),
                'aisle'                   => $location->getAisle(),
                'rack'                    => $location->getRack(),
                'shelf'                   => $location->getShelf(),
                'bin'                     => $location->getBin(),
                'max_weight_grams'        => $location->getMaxWeightGrams(),
                'max_volume_cubic_meters' => $location->getMaxVolumeCubicMeters(),
                'grid_x'                  => $location->getGridX(),
                'grid_y'                  => $location->getGridY(),
                'width'                   => $location->getWidth(),
                'height'                  => $location->getHeight()
            ]
        );
    }

    public function findById(LocationId $id): ?WarehouseLocation
    {
        $model = WarehouseLocationModel::find($id->getValue());
        if (!$model) {
            return null;
        }

        return new WarehouseLocation(
            new LocationId($model->id),
            $model->warehouse_id,
            $model->zone,
            $model->aisle,
            $model->rack,
            $model->shelf,
            $model->bin,
            $model->max_weight_grams,
            $model->max_volume_cubic_meters,
            (int) ($model->grid_x ?? 0),
            (int) ($model->grid_y ?? 0),
            (int) ($model->width ?? 1),
            (int) ($model->height ?? 1)
        );
    }

    public function delete(LocationId $id): void
    {
        WarehouseLocationModel::where('id', $id->getValue())->delete();
    }

    /**
     * @return WarehouseLocation[]
     */
    public function findAll(): array
    {
        $models = WarehouseLocationModel::all();
        $results = [];
        foreach ($models as $model) {
            $results[] = new WarehouseLocation(
                new LocationId($model->id),
                $model->warehouse_id,
                $model->zone,
                $model->aisle,
                $model->rack,
                $model->shelf,
                $model->bin,
                $model->max_weight_grams,
                $model->max_volume_cubic_meters,
                (int) ($model->grid_x ?? 0),
                (int) ($model->grid_y ?? 0),
                (int) ($model->width ?? 1),
                (int) ($model->height ?? 1)
            );
        }
        return $results;
    }
}
