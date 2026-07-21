<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Entities\DemandForecast;
use InventoryApp\Domain\Inventory\Repositories\DemandForecastRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Infrastructure\Models\DemandForecastModel;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class EloquentDemandForecastRepository implements DemandForecastRepositoryInterface
{
    public function save(DemandForecast $forecast): void
    {
        $id = $forecast->id->getValue();
        if (empty($id)) {
            $id = Uuid::uuid4()->toString();
        }

        DemandForecastModel::updateOrCreate(
            [
                'sku' => $forecast->sku->getValue(),
                'location_id' => $forecast->locationId->getValue(),
                'period_start' => $forecast->periodStart->format('Y-m-d H:i:s'),
                'period_end' => $forecast->periodEnd->format('Y-m-d H:i:s'),
            ],
            [
                'id' => $id,
                'forecasted_quantity' => $forecast->forecastedQuantity,
                'confidence_level' => $forecast->confidenceLevel,
                'created_at' => $forecast->createdAt->format('Y-m-d H:i:s'),
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findForecast(
        SKU $sku,
        LocationId $locationId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd
    ): ?DemandForecast {
        $model = DemandForecastModel::where('sku', $sku->getValue())
            ->where('location_id', $locationId->getValue())
            ->where('period_start', $periodStart->format('Y-m-d H:i:s'))
            ->where('period_end', $periodEnd->format('Y-m-d H:i:s'))
            ->first();

        if (!$model) {
            return null;
        }

        return $this->hydrate($model);
    }

    /**
     * @return DemandForecast[]
     */
    public function findAllForLocation(LocationId $locationId): array
    {
        $models = DemandForecastModel::where('location_id', $locationId->getValue())
            ->orderBy('period_start', 'asc')
            ->get();

        $forecasts = [];
        foreach ($models as $model) {
            $forecasts[] = $this->hydrate($model);
        }

        return $forecasts;
    }

    private function hydrate(DemandForecastModel $model): DemandForecast
    {
        return new DemandForecast(
            new DemandForecastId($model->id),
            new SKU($model->sku),
            new LocationId($model->location_id),
            (int) $model->forecasted_quantity,
            new DateTimeImmutable($model->period_start),
            new DateTimeImmutable($model->period_end),
            (float) $model->confidence_level,
            new DateTimeImmutable($model->created_at)
        );
    }
}
