<?php

namespace InventoryApp\Domain\Accounting\Repositories;

use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;

interface CostLayerRepositoryInterface
{
    /** @return InventoryCostLayer[] */
    public function getActiveLayers(string $variantId, string $orderBy = 'received_at ASC'): array;

    public function save(InventoryCostLayer $layer): void;

    /**
     * @param InventoryCostLayer[] $layers
     */
    public function saveBatch(array $layers): void;

    public function findBySerial(string $variantId, string $serialNumber): ?InventoryCostLayer;

    /**
     * @param string[] $serialNumbers
     * @return InventoryCostLayer[]
     */
    public function findBySerials(string $variantId, array $serialNumbers): array;
}
     * @param string[] $variantIds
     * @return array<string, InventoryCostLayer[]>
    public function getActiveLayersByVariantIds(array $variantIds, string $orderBy = 'received_at ASC'): array;
    

    

}



{
    

    

}
