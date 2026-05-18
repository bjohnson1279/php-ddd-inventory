<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\InventoryCount;
// In a real Laravel app, you might inject a UUID generator here

class StartInventoryCount
{
    private InventoryCountRepositoryInterface $countRepository;

    public function __construct(InventoryCountRepositoryInterface $countRepository)
    {
        $this->countRepository = $countRepository;
    }

    public function execute(string $countId): void
    {
        // Start a new inventory count aggregate
        $inventoryCount = InventoryCount::start($countId);
        
        $this->countRepository->save($inventoryCount);
    }
}
