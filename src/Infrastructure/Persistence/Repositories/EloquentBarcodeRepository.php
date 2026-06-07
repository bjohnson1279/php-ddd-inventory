<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Barcode\Aggregates\VariantBarcodeSet;
use InventoryApp\Domain\Barcode\Aggregates\BarcodeAssignment;
use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSource;
use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Infrastructure\Models\BarcodeModel;
use Ramsey\Uuid\Uuid;

class EloquentBarcodeRepository implements BarcodeRepositoryInterface
{
    public function registerAssignment(string $variantId, Barcode $barcode, BarcodeSource $source, bool $isPrimary = false): void
    {
        // Global uniqueness check
        $exists = BarcodeModel::whereRaw('LOWER(value) = ?', [strtolower(trim($barcode->value))])->exists();
        if ($exists) {
            throw new \DomainException('Barcode value already registered');
        }

        BarcodeModel::create([
            'id'         => Uuid::uuid4()->toString(),
            'variant_id' => $variantId,
            'value'      => $barcode->value,
            'symbology'  => $barcode->symbology->value,
            'source'     => $source->value,
            'is_primary' => $isPrimary,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findVariantByBarcodeValue(string $value): ?string
    {
        $model = BarcodeModel::whereRaw('LOWER(value) = ?', [strtolower(trim($value))])->first();
        return $model ? $model->variant_id : null;
    }

    public function findSetForVariant(string $variantId): VariantBarcodeSet
    {
        $models = BarcodeModel::where('variant_id', $variantId)->get();
        $set = new VariantBarcodeSet($variantId);

        // Reconstruct assignments
        // To bypass the protected/private constructor and array access limits,
        // we can assign via reflection or simply invoke the domain assign method.
        // Wait, the assign() method creates a standard BarcodeAssignment, but we
        // want to preserve the DB id and timestamp.
        // Let's use Reflection to populate the assignments directly.
        $reflector = new \ReflectionClass($set);
        $prop = $reflector->getProperty('assignments');
        $prop->setAccessible(true);
        
        $assignments = [];
        foreach ($models as $model) {
            $barcode = new Barcode(BarcodeSymbology::from($model->symbology), $model->value);
            $assignment = new BarcodeAssignment(
                $model->id,
                $model->variant_id,
                $barcode,
                BarcodeSource::from($model->source),
                $model->is_primary,
                new \DateTimeImmutable($model->created_at ?: 'now')
            );
            $assignments[$assignment->id] = $assignment;
        }
        $prop->setValue($set, $assignments);

        return $set;
    }

    public function saveSet(VariantBarcodeSet $set): void
    {
        \Illuminate\Database\Capsule\Manager::transaction(function () use ($set) {
            // naive: remove existing assignments for variant, then append
            BarcodeModel::where('variant_id', $set->variantId)->delete();

            $barcodesData = [];
            foreach ($set->all() as $a) {
                $id = $a->id;
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
                    $id = Uuid::uuid4()->toString();
                }

                $barcodesData[] = [
                    'id'         => $id,
                    'variant_id' => $a->variantId,
                    'value'      => $a->barcode->value,
                    'symbology'  => $a->barcode->symbology->value,
                    'source'     => $a->source->value,
                    'is_primary' => $a->isPrimary,
                    'created_at' => $a->assignedAt->format('Y-m-d H:i:s'),
                ];
            }

            if (!empty($barcodesData)) {
                foreach (array_chunk($barcodesData, 500) as $chunk) {
                    BarcodeModel::insert($chunk);
                }
            }
        });
    }
}
