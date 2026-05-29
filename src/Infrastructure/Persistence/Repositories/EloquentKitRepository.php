<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Kit\ValueObjects\KitComponent;
use InventoryApp\Infrastructure\Models\KitModel;
use InventoryApp\Infrastructure\Models\KitComponentModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use Ramsey\Uuid\Uuid;

class EloquentKitRepository implements KitRepositoryInterface
{
    public function save(Kit $kit): void
    {
        Capsule::transaction(function () use ($kit) {
            KitModel::updateOrCreate(
                ['id' => $kit->id],
                [
                    'sku'  => $kit->sku,
                    'name' => $kit->name,
                ]
            );

            // Re-sync components
            KitComponentModel::where('kit_id', $kit->id)->delete();

            foreach ($kit->components() as $component) {
                KitComponentModel::create([
                    'id'         => Uuid::uuid4()->toString(),
                    'kit_id'     => $kit->id,
                    'variant_id' => $component->variantId,
                    'quantity'   => $component->quantity,
                ]);
            }
        });
    }

    public function findBySku(string $sku): ?Kit
    {
        $model = KitModel::with('components')->whereRaw('LOWER(sku) = ?', [strtolower(trim($sku))])->first();
        if (!$model) {
            return null;
        }
        return $this->hydrate($model);
    }

    public function findOrFail(string $id): Kit
    {
        $model = KitModel::with('components')->find($id);
        if (!$model) {
            throw new \DomainException('Kit not found');
        }
        return $this->hydrate($model);
    }

    private function hydrate(KitModel $model): Kit
    {
        $kit = new Kit($model->id, $model->sku, $model->name);

        $components = [];
        foreach ($model->components as $c) {
            $components[] = new KitComponent($c->variant_id, (int)$c->quantity);
        }

        $reflector = new \ReflectionClass($kit);
        $prop = $reflector->getProperty('components');
        $prop->setAccessible(true);
        $prop->setValue($kit, $components);

        return $kit;
    }
}
