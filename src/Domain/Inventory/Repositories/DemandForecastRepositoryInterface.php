<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Entities\DemandForecast;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;

interface DemandForecastRepositoryInterface
{
    public function save(DemandForecast $forecast): void;
    
    public function findForecast(
        SKU $sku,
        LocationId $locationId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd
    ): ?DemandForecast;
    
    /**
     * @return DemandForecast[]
     */
    public function findAllForLocation(LocationId $locationId): array;
}
