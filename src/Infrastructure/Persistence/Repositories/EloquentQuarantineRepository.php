<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface;
use InventoryApp\Domain\Returns\Aggregates\QuarantineItem;
use InventoryApp\Domain\Returns\Enums\QuarantineStatus;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Infrastructure\Models\QuarantineItemModel;
use DateTimeImmutable;

class EloquentQuarantineRepository implements QuarantineRepositoryInterface
{
    private function ensureUuid(string $id): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return strtolower($id);
        }
        $hash = substr(hash('sha256', $id), 0, 32);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    private function mapToDomain(QuarantineItemModel $model): QuarantineItem
    {
        return new QuarantineItem(
            $model->id,
            $model->variant_id,
            $model->quantity,
            $model->reason,
            new LocationId($model->location_id),
            new TenantId($model->tenant_id),
            QuarantineStatus::from($model->status),
            new DateTimeImmutable($model->created_at),
            $model->resolved_at ? new DateTimeImmutable($model->resolved_at) : null
        );
    }

    public function save(QuarantineItem $item): void
    {
        $dbId = $this->ensureUuid($item->getId());

        QuarantineItemModel::updateOrCreate(
            ['id' => $dbId],
            [
                'tenant_id' => $item->getTenantId()->getValue(),
                'variant_id' => $this->ensureUuid($item->getVariantId()),
                'quantity' => $item->getQuantity(),
                'reason' => $item->getReason(),
                'status' => $item->getStatus()->value,
                'location_id' => $item->getLocationId()->getValue(),
                'created_at' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
                'resolved_at' => $item->getResolvedAt() ? $item->getResolvedAt()->format('Y-m-d H:i:s') : null
            ]
        );
    }

    public function findById(string $id): ?QuarantineItem
    {
        $dbId = $this->ensureUuid($id);
        $model = QuarantineItemModel::find($dbId);
        if (!$model) {
            return null;
        }
        return $this->mapToDomain($model);
    }

    public function findAllByTenant(string $tenantId): array
    {
        $models = QuarantineItemModel::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        $results = [];
        foreach ($models as $model) {
            $results[] = $this->mapToDomain($model);
        }
        return $results;
    }
}
