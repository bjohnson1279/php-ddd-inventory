<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Rfid\RfidTag;
use InventoryApp\Domain\Rfid\RfidTagRepositoryInterface;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Infrastructure\Models\RfidTagModel;

class EloquentRfidTagRepository implements RfidTagRepositoryInterface
{
    /**
     * @param string $tenantId
     * @param string[] $epcs
     * @return RfidTag[]
     */
    public function findByEpcs(string $tenantId, array $epcs): array
    {
        if (empty($epcs)) {
            return [];
        }

        $models = RfidTagModel::whereIn('epc', $epcs)->get();

        $tags = [];
        foreach ($models as $model) {
            $tags[] = $this->hydrate($model);
        }

        return $tags;
    }

    public function findByEpc(string $tenantId, string $epc): ?RfidTag
    {
        $model = RfidTagModel::where('epc', $epc)->first();
        return $model ? $this->hydrate($model) : null;
    }

    public function save(string $tenantId, RfidTag $tag): void
    {
        RfidTagModel::updateOrCreate(
            ['epc' => $tag->epc],
            [
                'sku' => $tag->sku,
                'serial_number' => $tag->serialNumber->value,
                'status' => $tag->status,
                'last_seen_at' => $tag->lastSeenAt?->format('Y-m-d H:i:s'),
                'last_location' => $tag->lastLocation,
            ]
        );
    }

    private function hydrate(RfidTagModel $model): RfidTag
    {
        return new RfidTag(
            epc: $model->epc,
            sku: $model->sku,
            serialNumber: new SerialNumber($model->serial_number),
            status: $model->status,
            lastSeenAt: $model->last_seen_at ? new \DateTimeImmutable($model->last_seen_at) : null,
            lastLocation: $model->last_location
        );
    }
}
