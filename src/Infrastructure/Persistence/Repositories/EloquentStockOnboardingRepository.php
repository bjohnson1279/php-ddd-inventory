<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;
use InventoryApp\Domain\Inventory\Repositories\StockOnboardingRepositoryInterface;
use InventoryApp\Infrastructure\Models\StockOnboardingModel;
use InventoryApp\Infrastructure\Models\StockOnboardingItemModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use Ramsey\Uuid\Uuid;

class EloquentStockOnboardingRepository implements StockOnboardingRepositoryInterface
{
    public function save(StockOnboarding $onboarding): void
    {
        Capsule::transaction(function () use ($onboarding) {
            StockOnboardingModel::updateOrCreate(
                ['id' => $onboarding->id],
                [
                    'tenant_id'   => $onboarding->tenantId,
                    'location_id' => $onboarding->locationId,
                    'as_of_date'  => $onboarding->asOfDate->format('Y-m-d'),
                    'status'      => $onboarding->isSubmitted() ? 'submitted' : 'draft',
                ]
            );

            // Re-sync items: delete existing and insert new
            StockOnboardingItemModel::where('onboarding_id', $onboarding->id)->delete();

            $itemsData = [];
            foreach ($onboarding->items() as $item) {
                $itemsData[] = [
                    'id'              => Uuid::uuid4()->toString(),
                    'onboarding_id'   => $onboarding->id,
                    'variant_id'      => $item->variantId,
                    'quantity'        => $item->quantity,
                    'unit_cost_cents' => $item->unitCostCents,
                ];
            }

            if (!empty($itemsData)) {
                foreach (array_chunk($itemsData, 500) as $chunk) {
                    StockOnboardingItemModel::insert($chunk);
                }
            }
        });
    }

    public function findOrFail(string $id): StockOnboarding
    {
        $model = StockOnboardingModel::with('items')->find($id);
        if (!$model) {
            throw new \DomainException('Onboarding not found');
        }

        $o = new StockOnboarding($model->id, $model->tenant_id, $model->location_id, new \DateTimeImmutable($model->as_of_date));
        foreach ($model->items as $item) {
            $o->setItem($item->variant_id, $item->quantity, $item->unit_cost_cents);
        }

        if ($model->status === 'submitted') {
            $o->submit();
        }

        return $o;
    }
}
