<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\Aggregates\StatusTransition;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Infrastructure\Models\SerializedItemModel;

class EloquentSerializedItemRepository implements SerializedItemRepositoryInterface
{
    public function isRegistered(SerialNumber $serial, string $tenantId): bool
    {
        return SerializedItemModel::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(serial_number) = ?', [strtolower(trim($serial->value))])
            ->exists();
    }

    public function save(SerializedItem $item): void
    {
        $history = array_map(function ($t) {
            return [
                'from'        => $t->from->value,
                'to'          => $t->to->value,
                'reason'      => $t->reason,
                'actorId'     => $t->actorId,
                'referenceId' => $t->referenceId,
                'occurredAt'  => $t->occurredAt->format(\DateTimeInterface::ATOM),
            ];
        }, $item->history());

        SerializedItemModel::updateOrCreate(
            ['id' => $item->id],
            [
                'variant_id'    => $item->variantId,
                'serial_number' => $item->serialNumber->value,
                'tenant_id'     => $item->tenantId,
                'location_id'   => $item->locationId(),
                'status'        => $item->status()->value,
                'history'       => $history,
            ]
        );
    }

    public function findBySerialOrFail(SerialNumber $serial, string $tenantId): SerializedItem
    {
        $item = $this->findBySerial($serial, $tenantId);
        if ($item === null) {
            throw new \DomainException('Serial not found');
        }
        return $item;
    }

    public function findBySerial(SerialNumber $serial, string $tenantId): ?SerializedItem
    {
        $model = SerializedItemModel::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(serial_number) = ?', [strtolower(trim($serial->value))])
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function findById(string $id): ?SerializedItem
    {
        $model = SerializedItemModel::find($id);
        return $model ? $this->hydrate($model) : null;
    }

    /** @return SerializedItem[] */
    public function findByVariant(string $variantId, ?SerializedItemStatus $status = null): array
    {
        $query = SerializedItemModel::where('variant_id', $variantId);
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->get()->map(fn($model) => $this->hydrate($model))->all();
    }

    public function countByStatus(string $variantId, SerializedItemStatus $status): int
    {
        return SerializedItemModel::where('variant_id', $variantId)
            ->where('status', $status->value)
            ->count();
    }

    private function hydrate(SerializedItemModel $model): SerializedItem
    {
        $serial = new SerialNumber($model->serial_number);
        $item = new SerializedItem(
            $model->id,
            $model->variant_id,
            $serial,
            $model->tenant_id,
            $model->location_id ?? '',
            SerializedItemStatus::from($model->status)
        );

        // Reconstruct history
        $historyData = $model->history ?: [];
        $history = [];
        foreach ($historyData as $h) {
            $history[] = new StatusTransition(
                SerializedItemStatus::from($h['from']),
                SerializedItemStatus::from($h['to']),
                $h['reason'],
                $h['actorId'],
                $h['referenceId'] ?? null,
                new \DateTimeImmutable($h['occurredAt'])
            );
        }

        $reflector = new \ReflectionClass($item);
        $prop = $reflector->getProperty('history');
        $prop->setAccessible(true);
        $prop->setValue($item, $history);

        return $item;
    }
}
