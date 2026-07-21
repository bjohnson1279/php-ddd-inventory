<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Uom\Repositories\ProductUomConfigurationRepositoryInterface;
use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;
use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Enums\UomCategory;
use InventoryApp\Domain\Uom\Entities\ConversionRule;
use InventoryApp\Infrastructure\Models\ProductUomConfigurationModel;
use InventoryApp\Infrastructure\Models\UomConversionRuleModel;
use Illuminate\Database\Capsule\Manager as Capsule;

class EloquentProductUomConfigurationRepository implements ProductUomConfigurationRepositoryInterface
{
    private function serializeUnit(UnitOfMeasure $unit): string
    {
        return $unit->name . '|' . $unit->abbreviation . '|' . $unit->category->value;
    }

    private function deserializeUnit(string $serialized): UnitOfMeasure
    {
        $parts = explode('|', $serialized);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid serialized unit of measure: {$serialized}");
        }
        return new UnitOfMeasure($parts[0], $parts[1], UomCategory::from($parts[2]));
    }

    public function save(ProductUomConfiguration $config): void
    {
        Capsule::transaction(function () use ($config) {
            ProductUomConfigurationModel::updateOrCreate(
                ['id' => $config->id],
                [
                    'variant_id'    => $config->variantId,
                    'base_unit'     => $this->serializeUnit($config->baseUnit()),
                    'purchase_unit' => $config->purchaseUnit() ? $this->serializeUnit($config->purchaseUnit()) : null,
                    'sale_unit'     => $config->saleUnit() ? $this->serializeUnit($config->saleUnit()) : null,
                ]
            );

            // Re-sync rules
            UomConversionRuleModel::where('configuration_id', $config->id)->delete();

            // Bulk insert to avoid N+1 query and handle large arrays with chunking
            $rulesData = [];
            foreach ($config->conversionRules() as $rule) {
                $rulesData[] = [
                    'id'               => $rule->id,
                    'configuration_id' => $config->id,
                    'unit'             => $this->serializeUnit($rule->unit),
                    'factor_to_base'   => $rule->factorToBase,
                    'label'            => $rule->label,
                ];
            }

            if (!empty($rulesData)) {
                foreach (array_chunk($rulesData, 500) as $chunk) {
                    UomConversionRuleModel::insert($chunk);
                }
            }
        });
    }

    public function findByVariant(string $variantId): ?ProductUomConfiguration
    {
        $model = ProductUomConfigurationModel::with('rules')->where('variant_id', $variantId)->first();
        if (!$model) {
            return null;
        }
        return $this->hydrate($model);
    }

    public function findOrFail(string $id): ProductUomConfiguration
    {
        $model = ProductUomConfigurationModel::with('rules')->find($id);
        if (!$model) {
            throw new \DomainException('Product UoM configuration not found');
        }
        return $this->hydrate($model);
    }

    private function hydrate(ProductUomConfigurationModel $model): ProductUomConfiguration
    {
        $baseUnit = $this->deserializeUnit($model->base_unit);
        $config = new ProductUomConfiguration($model->id, $model->variant_id, $baseUnit);

        // Reflection is needed to set the private purchaseUnit/saleUnit if they exist
        $reflector = new \ReflectionClass($config);

        if ($model->purchase_unit) {
            $purchaseProp = $reflector->getProperty('purchaseUnit');
            $purchaseProp->setAccessible(true);
            $purchaseProp->setValue($config, $this->deserializeUnit($model->purchase_unit));
        }
        if ($model->sale_unit) {
            $saleProp = $reflector->getProperty('saleUnit');
            $saleProp->setAccessible(true);
            $saleProp->setValue($config, $this->deserializeUnit($model->sale_unit));
        }

        // Reconstruct conversionRules via Reflection
        $rules = [];
        foreach ($model->rules as $ruleModel) {
            $rules[] = new ConversionRule(
                $ruleModel->id,
                $this->deserializeUnit($ruleModel->unit),
                (float)$ruleModel->factor_to_base,
                $ruleModel->label
            );
        }

        $prop = $reflector->getProperty('conversionRules');
        $prop->setAccessible(true);
        $prop->setValue($config, $rules);

        return $config;
    }
}
