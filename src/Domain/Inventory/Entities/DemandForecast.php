<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;
use InvalidArgumentException;

final class DemandForecast
{
    public function __construct(
        public readonly DemandForecastId $id,
        public readonly SKU $sku,
        public readonly LocationId $locationId,
        public readonly int $forecastedQuantity,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        public readonly float $confidenceLevel,
        public readonly DateTimeImmutable $createdAt
    ) {
        if ($forecastedQuantity < 0) {
            throw new InvalidArgumentException("Forecasted quantity cannot be negative.");
        }
        if ($confidenceLevel < 0.0 || $confidenceLevel > 1.0) {
            throw new InvalidArgumentException("Confidence level must be between 0.0 and 1.0.");
        }
        if ($periodStart >= $periodEnd) {
            throw new InvalidArgumentException("Period start must be before period end.");
        }
    }
}
