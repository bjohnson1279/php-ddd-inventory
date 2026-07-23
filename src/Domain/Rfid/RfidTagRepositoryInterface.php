<?php

namespace InventoryApp\Domain\Rfid;

interface RfidTagRepositoryInterface
{
    /**
     * @param string $tenantId
     * @param string[] $epcs
     * @return RfidTag[]
     */
    public function findByEpcs(string $tenantId, array $epcs): array;

    public function findByEpc(string $tenantId, string $epc): ?RfidTag;

    public function save(string $tenantId, RfidTag $tag): void;
}
