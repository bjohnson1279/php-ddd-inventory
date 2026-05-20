<?php

namespace InventoryApp\Domain\Accounting\Repositories;

use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;

interface CostLayerRepositoryInterface
{
    /** @return InventoryCostLayer[] */
    public function getActiveLayers(string $variantId, string $orderBy = 'received_at ASC'): array;
    
    public function save(InventoryCostLayer $layer): void;
    
    public function findBySerial(string $variantId, string $serialNumber): ?InventoryCostLayer;
}
