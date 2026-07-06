<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Entities\AuditDiscrepancy;
use InventoryApp\Domain\Inventory\Repositories\AuditDiscrepancyRepositoryInterface;
use InventoryApp\Infrastructure\Models\AuditDiscrepancyModel;

class EloquentAuditDiscrepancyRepository implements AuditDiscrepancyRepositoryInterface
{
    public function save(AuditDiscrepancy $discrepancy): void
    {
        AuditDiscrepancyModel::updateOrCreate(
            ['id' => $discrepancy->id],
            [
                'tenant_id' => $discrepancy->tenantId,
                'type' => $discrepancy->type,
                'reference_id' => $discrepancy->referenceId,
                'external_ref_id' => $discrepancy->externalRefId,
                'description' => $discrepancy->description,
                'status' => $discrepancy->status,
                'occurred_at' => $discrepancy->occurredAt?->format('Y-m-d H:i:s'),
                'resolved_at' => $discrepancy->resolvedAt?->format('Y-m-d H:i:s'),
                'resolution_notes' => $discrepancy->resolutionNotes,
            ]
        );
    }

    public function find(string $id): ?AuditDiscrepancy
    {
        $model = AuditDiscrepancyModel::find($id);
        if (!$model) {
            return null;
        }
        return $this->toDomain($model);
    }

    public function findOpen(string $tenantId, string $type, string $referenceId): ?AuditDiscrepancy
    {
        $model = AuditDiscrepancyModel::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('reference_id', $referenceId)
            ->where('status', 'OPEN')
            ->first();
        if (!$model) {
            return null;
        }
        return $this->toDomain($model);
    }

    public function findAll(string $tenantId, ?string $status = null): array
    {
        $query = AuditDiscrepancyModel::where('tenant_id', $tenantId);
        if ($status !== null) {
            $query->where('status', $status);
        }
        $models = $query->orderBy('occurred_at', 'desc')->get();
        
        $result = [];
        foreach ($models as $model) {
            $result[] = $this->toDomain($model);
        }
        return $result;
    }

    private function toDomain(AuditDiscrepancyModel $model): AuditDiscrepancy
    {
        return new AuditDiscrepancy(
            $model->id,
            $model->tenant_id,
            $model->type,
            $model->reference_id,
            $model->external_ref_id,
            $model->description,
            $model->status,
            $model->occurred_at ? new \DateTimeImmutable($model->occurred_at) : null,
            $model->resolved_at ? new \DateTimeImmutable($model->resolved_at) : null,
            $model->resolution_notes
        );
    }
}
