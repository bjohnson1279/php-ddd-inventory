<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Entities\RMAItem;
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use InventoryApp\Domain\Returns\Enums\RMAItemStatus;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Infrastructure\Models\RMAModel;
use InventoryApp\Infrastructure\Models\RMAItemModel;
use DateTimeImmutable;

class EloquentRMARepository implements RMARepositoryInterface
{
    private function ensureUuid(string $id): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return strtolower($id);
        }
        $hash = md5($id);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    private function mapToDomain(RMAModel $model): RMA
    {
        $items = [];
        foreach ($model->items as $item) {
            $items[] = new RMAItem(
                $item->id,
                $item->variant_id,
                $item->quantity,
                $item->unit_cost_cents,
                $item->received_quantity,
                RMAItemStatus::from($item->status),
                $item->disposition ? RMADisposition::from($item->disposition) : null
            );
        }

        return new RMA(
            $model->id,
            $model->rma_number,
            new TenantId($model->tenant_id),
            $model->customer_id,
            new LocationId($model->location_id),
            RMAStatus::from($model->status),
            $items,
            new DateTimeImmutable($model->created_at),
            new DateTimeImmutable($model->updated_at)
        );
    }

    public function save(RMA $rma): void
    {
        $dbId = $this->ensureUuid($rma->getId());

        \Illuminate\Support\Facades\DB::transaction(function () use ($rma, $dbId) {
            RMAModel::updateOrCreate(
                ['id' => $dbId],
                [
                    'rma_number' => $rma->getRmaNumber(),
                    'tenant_id' => $rma->getTenantId()->getValue(),
                    'customer_id' => $rma->getCustomerId(),
                    'location_id' => $rma->getLocationId()->getValue(),
                    'status' => $rma->getStatus()->value,
                    'created_at' => $rma->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $rma->getUpdatedAt()->format('Y-m-d H:i:s')
                ]
            );

            foreach ($rma->getItems() as $item) {
                $itemDbId = $this->ensureUuid($item->getId());
                RMAItemModel::updateOrCreate(
                    ['id' => $itemDbId],
                    [
                        'rma_id' => $dbId,
                        'variant_id' => $this->ensureUuid($item->getVariantId()),
                        'quantity' => $item->getQuantity(),
                        'received_quantity' => $item->getReceivedQuantity(),
                        'unit_cost_cents' => $item->getUnitCostCents(),
                        'status' => $item->getStatus()->value,
                        'disposition' => $item->getDisposition() ? $item->getDisposition()->value : null,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                );
            }
        });
    }

    public function findById(string $id): ?RMA
    {
        $dbId = $this->ensureUuid($id);
        $model = RMAModel::with('items')->find($dbId);
        if (!$model) {
            return null;
        }
        return $this->mapToDomain($model);
    }

    public function findByNumber(string $rmaNumber): ?RMA
    {
        $model = RMAModel::with('items')->where('rma_number', $rmaNumber)->first();
        if (!$model) {
            return null;
        }
        return $this->mapToDomain($model);
    }

    public function findAllByTenant(string $tenantId): array
    {
        $models = RMAModel::with('items')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        $results = [];
        foreach ($models as $model) {
            $results[] = $this->mapToDomain($model);
        }
        return $results;
    }
}
