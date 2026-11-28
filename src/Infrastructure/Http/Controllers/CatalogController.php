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
            ]);

            $id = !empty($validated['id']) ? (string)$validated['id'] : Uuid::uuid4()->toString();
            $description = isset($validated['description']) && is_string($validated['description']) ? $validated['description'] : '';
            $department = isset($validated['department']) && is_string($validated['department']) ? $validated['department'] : 'General';
            $useCase->execute($id, $validated['name'], $description, $department);

            return new Response(['message' => 'Catalog product created successfully', 'id' => $id], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[CatalogController] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addVariant(RequestInterface $request, string $productId, AddVariant $useCase)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
            ]);

            $variantId = !empty($validated['id']) ? (string)$validated['id'] : Uuid::uuid4()->toString();
            $attributes = isset($validated['attributes']) && is_array($validated['attributes']) && count($validated['attributes']) > 0
                ? $validated['attributes']
                : ['default' => 'standard'];
            $price = isset($validated['price']) && is_numeric($validated['price']) ? (float)$validated['price'] : 0.0;
            $useCase->execute($productId, $variantId, $validated['sku'], $attributes, $price);

            return new Response(['message' => 'Variant added successfully', 'id' => $variantId], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[CatalogController] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
