<?php

namespace InventoryApp\Domain\Serial\Repositories;

use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;

interface SerializedItemRepositoryInterface
{
    public function isRegistered(SerialNumber $serial, string $tenantId): bool;

    public function save(SerializedItem $item): void;

    public function findBySerialOrFail(SerialNumber $serial, string $tenantId): SerializedItem;

    public function findBySerial(SerialNumber $serial, string $tenantId): ?SerializedItem;

    public function findById(string $id): ?SerializedItem;

    /** @return SerializedItem[] */
    public function findByVariant(string $variantId, ?SerializedItemStatus $status = null): array;

    public function countByStatus(string $variantId, SerializedItemStatus $status): int;
}
