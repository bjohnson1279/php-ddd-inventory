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
    }

    public function currentQuantity(string $variantId): int
    {
        return (int) LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId)
            ->sum('quantity');
    }

    /** @return LedgerEntry[] */
    public function entriesFor(string $variantId): array
    {
        return LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId)
            ->orderBy('occurred_at')
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

    public function hasAnyEntries(string $variantId, string $locationId): bool
    {
        return LedgerEntryModel::where('tenant_id', $this->tenantId)
            ->where('variant_id', $variantId)
            ->whereRaw("metadata->>'locationId' = ?", [$locationId])
            ->exists();
    }
}
