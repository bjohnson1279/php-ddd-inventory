<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Uom\Repositories\ProductUomConfigurationRepositoryInterface;
use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;
use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\Enums\UomCategory;
use Ramsey\Uuid\Uuid;
use Exception;

class UomController
{
    private function parseUnit(array $data): UnitOfMeasure
    {
        if (empty($data['name']) || empty($data['abbreviation']) || empty($data['category'])) {
            throw new \InvalidArgumentException('Unit of measure must have name, abbreviation, and category.');
        }

        $category = UomCategory::tryFrom($data['category']);
        if ($category === null) {
            throw new \InvalidArgumentException('Invalid unit of measure category.');
        }

        return new UnitOfMeasure(
            $data['name'],
            $data['abbreviation'],
            $category
        );
    }

    private function serializeUnit(UnitOfMeasure $unit): array
    {
        return [
            'name'         => $unit->name,
            'abbreviation' => $unit->abbreviation,
            'category'     => $unit->category->value,
        ];
    }

    public function create(RequestInterface $request, ProductUomConfigurationRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'variant_id' => 'required|string',
                'base_unit'  => 'required',
            ]);

            // Body parameters are array
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $baseUnitData = $body['base_unit'] ?? null;
            if (!is_array($baseUnitData)) {
                throw new \InvalidArgumentException('base_unit must be an object.');
            }

            $id = Uuid::uuid4()->toString();
            $baseUnit = $this->parseUnit($baseUnitData);

            $config = new ProductUomConfiguration($id, $validated['variant_id'], $baseUnit);
            $repo->save($config);

            return new Response([
                'message' => 'Product UoM configuration created successfully',
                'id'      => $id,
            ], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addRule(RequestInterface $request, string $id, ProductUomConfigurationRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'unit'           => 'required',
                'factor_to_base' => 'required',
            ]);

            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $unitData = $body['unit'] ?? null;
            if (!is_array($unitData)) {
                throw new \InvalidArgumentException('unit must be an object.');
            }

            $config = $repo->findOrFail($id);
            $unit = $this->parseUnit($unitData);
            $factor = (float)$validated['factor_to_base'];
            $label = $body['label'] ?? null;

            $config->addConversionRule($unit, $factor, $label);
            $repo->save($config);

            return new Response(['message' => 'Conversion rule added successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function removeRule(RequestInterface $request, string $id, ProductUomConfigurationRepositoryInterface $repo)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $unitData = $body['unit'] ?? null;
            if (!is_array($unitData)) {
                throw new \InvalidArgumentException('unit must be an object.');
            }

            $config = $repo->findOrFail($id);
            $unit = $this->parseUnit($unitData);

            $config->removeConversionRule($unit);
            $repo->save($config);

            return new Response(['message' => 'Conversion rule removed successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function setUnits(RequestInterface $request, string $id, ProductUomConfigurationRepositoryInterface $repo)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];

            $config = $repo->findOrFail($id);

            if (isset($body['purchase_unit']) && is_array($body['purchase_unit'])) {
                $config->setPurchaseUnit($this->parseUnit($body['purchase_unit']));
            }
            if (isset($body['sale_unit']) && is_array($body['sale_unit'])) {
                $config->setSaleUnit($this->parseUnit($body['sale_unit']));
            }

            $repo->save($config);

            return new Response(['message' => 'UoM configuration units updated successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function show(RequestInterface $request, string $variantId, ProductUomConfigurationRepositoryInterface $repo)
    {
        try {
            $config = $repo->findByVariant($variantId);
            if (!$config) {
                return new Response(['error' => 'Product UoM configuration not found'], 404);
            }
            return new Response($this->serializeConfig($config), 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function showById(RequestInterface $request, string $id, ProductUomConfigurationRepositoryInterface $repo)
    {
        try {
            $config = $repo->findOrFail($id);
            return new Response($this->serializeConfig($config), 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function serializeConfig(ProductUomConfiguration $config): array
    {
        $rules = array_map(function ($rule) {
            return [
                'id'             => $rule->id,
                'unit'           => $this->serializeUnit($rule->unit),
                'factor_to_base' => $rule->factorToBase,
                'label'          => $rule->label,
            ];
        }, $config->conversionRules());

        return [
            'id'            => $config->id,
            'variant_id'    => $config->variantId,
            'base_unit'     => $this->serializeUnit($config->baseUnit()),
            'purchase_unit' => $this->serializeUnit($config->purchaseUnit()),
            'sale_unit'     => $this->serializeUnit($config->saleUnit()),
            'rules'         => $rules,
        ];
    }
}
