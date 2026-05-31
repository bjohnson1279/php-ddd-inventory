<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use InventoryApp\Infrastructure\Models\CostLayerModel;
use DateTimeImmutable;

class EloquentCostLayerRepository implements CostLayerRepositoryInterface
{
    public function __construct(private readonly string $tenantId) {}

    /** @return InventoryCostLayer[] */
    public function getActiveLayers(string $variantId, string $orderBy = 'received_at ASC'): array
    {
        $direction = str_contains(strtoupper($orderBy), 'DESC') ? 'desc' : 'asc';
        
        $models = CostLayerModel::where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at', $direction)
            ->get();

        return $models->map(fn($model) => $this->hydrate($model))->all();
    }

    public function save(InventoryCostLayer $layer): void
    {
        CostLayerModel::updateOrCreate(
            ['id' => $layer->id],
            [
                'tenant_id'          => $layer->tenantId,
                'variant_id'         => $layer->variantId,
                'original_quantity'  => $layer->originalQuantity,
                'remaining_quantity' => $layer->remainingQuantity(),
                'unit_cost_cents'    => $layer->unitCostCents,
                'purchase_order_id'  => $layer->purchaseOrderId,
                'received_at'        => $layer->receivedAt->format('Y-m-d H:i:s'),
                'serial_number'      => $layer->serialNumber,
            ]
        );
    }

    public function findBySerial(string $variantId, string $serialNumber): ?InventoryCostLayer
    {
        $model = CostLayerModel::where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId)
            ->where('serial_number', $serialNumber)
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    private function hydrate(CostLayerModel $model): InventoryCostLayer
    {
        $layer = new InventoryCostLayer(
            $model->id,
            $model->variant_id,
            $model->tenant_id,
            $model->original_quantity,
            $model->unit_cost_cents,
            new DateTimeImmutable($model->received_at),
            $model->purchase_order_id
        );

        $layer->setRemainingQuantity($model->remaining_quantity);
        $layer->serialNumber = $model->serial_number;

        return $layer;
    }
}
