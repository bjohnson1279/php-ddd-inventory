<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
use Exception;
// use Ramsey\Uuid\Uuid; // Assuming UUID generator is available

class CatalogController
{
    public function createProduct($request, CreateProductCatalog $useCase): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'description' => 'required|string',
                'department' => 'required|string'
            ]);

            $id = uniqid('prod_'); // Using uniqid for simplicity in this example
            $useCase->execute($id, $validated['name'], $validated['description'], $validated['department']);

            return new JsonResponse(['message' => 'Catalog product created successfully', 'id' => $id], 201);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function addVariant(Request $request, string $productId, AddVariant $useCase): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'attributes' => 'required|array',
                'price' => 'required|numeric|min:0'
            ]);

            $variantId = uniqid('var_'); // Using uniqid for simplicity in this example
            $useCase->execute($productId, $variantId, $validated['sku'], $validated['attributes'], $validated['price']);

            return new JsonResponse(['message' => 'Variant added successfully', 'id' => $variantId], 201);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
