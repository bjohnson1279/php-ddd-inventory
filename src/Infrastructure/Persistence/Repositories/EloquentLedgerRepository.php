<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use InventoryApp\Infrastructure\Models\LedgerEntryModel;
use Ramsey\Uuid\Uuid;

/**
 * Durable, Postgres-backed implementation of LedgerRepositoryInterface.
 *
 * The ledger is append-only. Rows are never updated or deleted.
 * All reads are scoped to the tenant passed at construction time.
 */
class EloquentLedgerRepository implements LedgerRepositoryInterface
{
    public function __construct(private readonly string $tenantId) {}

    public function append(LedgerEntry $entry): void
    {
        $this->appendAll([$entry]);
    }

    public function appendAll(array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $insertData = [];

        foreach ($entries as $entry) {
            $insertData[] = [
                'id'           => $entry->id ?: Uuid::uuid4()->toString(),
                'tenant_id'    => $this->tenantId,
                'variant_id'   => $entry->variantId,
                'quantity'     => $entry->quantity,
                'reason'       => $entry->reason->value,
                'actor_id'     => $entry->actorId,
                'reference_id' => $entry->referenceId,
                'occurred_at'  => $entry->occurredAt->format('Y-m-d H:i:s'),
                'metadata'     => json_encode($entry->metadata),
                'created_at'   => $now,
            ];
        }

        LedgerEntryModel::insert($insertData);

            try {
                \InventoryApp\Domain\Compliance\Services\ComplianceLedgerService::logEvent(
                    $this->tenantId,
                    $entry->actorId ?: 'system',
                    'STOCK_ADJUSTED',
                    [
                        'variant_id'   => $entry->variantId,
                        'quantity'     => $entry->quantity,
                        'reason'       => $entry->reason->value,
                        'reference_id' => $entry->referenceId,
                        'occurred_at'  => $entry->occurredAt->format('Y-m-d H:i:s')
                    ]
                );
            } catch (\Throwable $e) {
                error_log('Failed to log event to compliance ledger: ' . $e->getMessage());
            }

                $metadata = is_string($entry->metadata) ? json_decode($entry->metadata, true) : $entry->metadata;
                $locId = $metadata['locationId'] ?? 'unknown';

                (new \InventoryApp\Application\Notification\Services\NotificationService())->createNotification(
                    "Stock Level Updated",
                    json_encode([
                        'sku'        => $entry->variantId,
                        'locationId' => $locId,
                        'quantity'   => (int) $entry->quantity
                    ]),
                    'stock_changed'
                error_log('Failed to create stock_changed notification: ' . $e->getMessage());
            }
        LedgerEntryModel::create([
            'id'           => $entry->id ?: Uuid::uuid4()->toString(),
            'tenant_id'    => $this->tenantId,
            'variant_id'   => $entry->variantId,
            'quantity'     => $entry->quantity,
            'reason'       => $entry->reason->value,
            'actor_id'     => $entry->actorId,
            'reference_id' => $entry->referenceId,
            'occurred_at'  => $entry->occurredAt->format('Y-m-d H:i:s'),
            'metadata'     => $entry->metadata,
            'created_at'   => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        try {
            \InventoryApp\Domain\Compliance\Services\ComplianceLedgerService::logEvent(
                $this->tenantId,
                $entry->actorId ?: 'system',
                'STOCK_ADJUSTED',
                [
                    'variant_id'   => $entry->variantId,
                    'quantity'     => $entry->quantity,
                    'reason'       => $entry->reason->value,
                    'reference_id' => $entry->referenceId,
                    'occurred_at'  => $entry->occurredAt->format('Y-m-d H:i:s')
                ]
            );
        } catch (\Throwable $e) {
            error_log('Failed to log event to compliance ledger: ' . $e->getMessage());
        }

            $metadata = is_string($entry->metadata) ? json_decode($entry->metadata, true) : $entry->metadata;
            $locId = $metadata['locationId'] ?? 'unknown';

            (new \InventoryApp\Application\Notification\Services\NotificationService())->createNotification(
                "Stock Level Updated",
                json_encode([
                    'sku'        => $entry->variantId,
                    'locationId' => $locId,
                    'quantity'   => (int) $entry->quantity
                ]),
                'stock_changed'
            error_log('Failed to create stock_changed notification: ' . $e->getMessage());
        }
    }

    public function currentQuantity(string $variantId): int
    {
        return (int) LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId)
            ->sum('quantity');
    }

    public function currentQuantities(array $variantIds): array
    {
        if (empty($variantIds)) {
            return [];
        }

        $results = LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->whereIn('variant_id', $variantIds)
            ->groupBy('variant_id')
            ->selectRaw('variant_id, SUM(quantity) as total')
            ->get();

        $map = [];
        foreach ($variantIds as $vId) {
            $map[$vId] = 0;
        }
        foreach ($results as $row) {
            $map[$row->variant_id] = (int) $row->total;
        }
        return $map;
    }

    /** @return LedgerEntry[] */
    public function entriesFor(string $variantId, ?string $locationId = null): array
    {
        $query = LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId);

        if ($locationId !== null) {
            $query->whereRaw("metadata->>'locationId' = ?", [$locationId]);
        }

        return $query->orderBy('occurred_at')
            ->get()
            ->map(fn($row) => new LedgerEntry(
                id:          $row->id,
                variantId:   $row->variant_id,
                quantity:    (int) $row->quantity,
                reason:      ReasonCode::from($row->reason),
                actorId:     $row->actor_id,
                referenceId: $row->reference_id,
                occurredAt:  new \DateTimeImmutable($row->occurred_at),
                metadata:    $row->metadata ?? [],
            ))
            ->all();
    }

    public function entriesForSkusAndLocation(array $variantIds, string $locationId): array
    {
        }

        return LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->whereRaw("metadata->>'locationId' = ?", [$locationId])
            ->orderBy('occurred_at')
    }

    public function hasAnyEntries(string $variantId, string $locationId): bool
    {
            ->exists();
    }

    public function findRecallEntries(string $lotNumber): array
    {
        $rows = LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->where('metadata->lotNumber', $lotNumber)
            ->orderBy('occurred_at', 'desc')

        return $rows->map(fn($row) => new LedgerEntry(
            id:          $row->id,
            variantId:   $row->variant_id,
            quantity:    (int) $row->quantity,
            reason:      ReasonCode::from($row->reason),
            actorId:     $row->actor_id,
            referenceId: $row->reference_id,
            occurredAt:  new \DateTimeImmutable($row->occurred_at),
            metadata:    $row->metadata ?? [],
        ))->all();
    }
}



{

    {
        LedgerEntryModel::create([
            'id'           => $entry->id ?: Uuid::uuid4()->toString(),
            'tenant_id'    => $this->tenantId,
            'variant_id'   => $entry->variantId,
            'quantity'     => $entry->quantity,
            'reason'       => $entry->reason->value,
            'actor_id'     => $entry->actorId,
            'reference_id' => $entry->referenceId,
            'occurred_at'  => $entry->occurredAt->format('Y-m-d H:i:s'),
            'metadata'     => $entry->metadata,
            'created_at'   => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        try {
            \InventoryApp\Domain\Compliance\Services\ComplianceLedgerService::logEvent(
                $this->tenantId,
                $entry->actorId ?: 'system',
                'STOCK_ADJUSTED',
                [
                    'variant_id'   => $entry->variantId,
                    'quantity'     => $entry->quantity,
                    'reason'       => $entry->reason->value,
                    'reference_id' => $entry->referenceId,
                    'occurred_at'  => $entry->occurredAt->format('Y-m-d H:i:s')
                ]
            );
        } catch (\Throwable $e) {
            error_log('Failed to log event to compliance ledger: ' . $e->getMessage());
        }

            $metadata = is_string($entry->metadata) ? json_decode($entry->metadata, true) : $entry->metadata;
            $locId = $metadata['locationId'] ?? 'unknown';

            (new \InventoryApp\Application\Notification\Services\NotificationService())->createNotification(
                "Stock Level Updated",
                json_encode([
                    'sku'        => $entry->variantId,
                    'locationId' => $locId,
                    'quantity'   => (int) $entry->quantity
                ]),
                'stock_changed'
            error_log('Failed to create stock_changed notification: ' . $e->getMessage());

        }


        }
    }

    {
    }

    {
        }


        }
        }
    }

    {

            $query->where('metadata->locationId', $locationId);
        }

    }

    {
        }

            ->where('metadata->locationId', $locationId)
    }

    {
    }

    {

    }
}



{

    {

        }


        }
    }

    {
    }

    {
        }


        }
        }
    }

    {

        }

    }

    {
        }

    }

    {
    }

    {

    }
}
