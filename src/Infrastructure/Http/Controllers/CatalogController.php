<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
use Ramsey\Uuid\Uuid;
use Exception;

class CatalogController
{
    public function createProduct(RequestInterface $request, CreateProductCatalog $useCase)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'description' => 'required|string',
                'department' => 'required|string'
            ]);

            $id = Uuid::uuid4()->toString();
            $useCase->execute($id, $validated['name'], $validated['description'], $validated['department']);

            return new Response(['message' => 'Catalog product created successfully', 'id' => $id], 201);
        } catch (\InvalidArgumentException | \ValidationException $e) {
            return new Response(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log('[CatalogController] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }

    public function addVariant(RequestInterface $request, string $productId, AddVariant $useCase)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'attributes' => 'required|array',
                'price' => 'required|numeric|min:0'
            ]);

            $variantId = Uuid::uuid4()->toString();
            $useCase->execute($productId, $variantId, $validated['sku'], $validated['attributes'], $validated['price']);

            return new Response(['message' => 'Variant added successfully', 'id' => $variantId], 201);
        } catch (\InvalidArgumentException | \ValidationException $e) {
            return new Response(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log('[CatalogController] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }
}
