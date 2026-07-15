<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Compliance\Repositories\ComplianceLedgerRepositoryInterface;
use InventoryApp\Domain\Compliance\Entities\ComplianceLedgerEntry;
use InventoryApp\Infrastructure\Models\ComplianceLedgerModel;
use DateTime;

class EloquentComplianceLedgerRepository implements ComplianceLedgerRepositoryInterface
{
    public function save(ComplianceLedgerEntry $entry): void
    {
        ComplianceLedgerModel::updateOrCreate(
            ['id' => $entry->getId()],
            [
                'tenant_id'       => $entry->getTenantId(),
                'actor_id'        => $entry->getActorId(),
                'event_type'      => $entry->getEventType(),
                'sequence_number' => $entry->getSequenceNumber(),
                'previous_hash'   => $entry->getPreviousHash(),
                'current_hash'    => $entry->getCurrentHash(),
                'signature'       => $entry->getSignature(),
                'payload'         => $entry->getPayload(),
                'created_at'      => $entry->getCreatedAt()->format('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * @return ComplianceLedgerEntry[]
     */
    public function findAll(string $tenantId = null): array
    {
        $query = ComplianceLedgerModel::query();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $models = $query->orderBy('sequence_number', 'asc')->get();

        $results = [];
        foreach ($models as $model) {
            $results[] = $this->hydrate($model);
        }
        return $results;
    }

    public function getLastEntry(string $tenantId = null): ?ComplianceLedgerEntry
    {
        $query = ComplianceLedgerModel::query();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $model = $query->orderBy('sequence_number', 'desc')->first();
        if (!$model) {
            return null;
        }
        return $this->hydrate($model);
    }

    private function hydrate(ComplianceLedgerModel $model): ComplianceLedgerEntry
    {
        return new ComplianceLedgerEntry(
            $model->id,
            $model->tenant_id,
            $model->actor_id,
            $model->event_type,
            (int) $model->sequence_number,
            $model->previous_hash,
            $model->current_hash,
            $model->signature,
            $model->payload,
            new DateTime($model->created_at)
        );
    }
}
