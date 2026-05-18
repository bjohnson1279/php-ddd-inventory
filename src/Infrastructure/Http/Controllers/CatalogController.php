<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
use Exception;
// use Ramsey\Uuid\Uuid; // Assuming UUID generator is available

class CatalogController
{
    public function createProduct($request, CreateProductCatalog $useCase)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'description' => 'required|string',
                'department' => 'required|string'
            ]);

            $id = \uuidv4(); // Use UUID to match DB UUID columns
            $useCase->execute($id, $validated['name'], $validated['description'], $validated['department']);

            return new Response(['message' => 'Catalog product created successfully', 'id' => $id], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addVariant($request, string $productId, AddVariant $useCase)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'attributes' => 'required|array',
                'price' => 'required|numeric|min:0'
            ]);

            $variantId = uniqid('var_'); // Using uniqid for simplicity in this example
            $useCase->execute($productId, $variantId, $validated['sku'], $validated['attributes'], $validated['price']);

            return new Response(['message' => 'Variant added successfully', 'id' => $variantId], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
