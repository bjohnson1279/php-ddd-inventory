<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

// use App\Http\Controllers\Controller; // Laravel base controller
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use Exception;

class InventoryController // extends Controller
{
    public function receive(Request $request, ReceiveStock $useCase): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'location_id' => 'required|string'
            ]);

            $useCase->execute($validated['sku'], $validated['location_id'], $validated['quantity']);

            return new JsonResponse(['message' => 'Stock received successfully'], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function dispatch(Request $request, DispatchStock $useCase): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'location_id' => 'required|string'
            ]);

            $useCase->execute($validated['sku'], $validated['location_id'], $validated['quantity']);

            return new JsonResponse(['message' => 'Stock dispatched successfully'], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function stockLevel(Request $request, string $sku, GetStockLevel $useCase): JsonResponse
    {
        try {
            $locationId = $request->query('location_id');
            $stock = $useCase->execute($sku, $locationId);

            return new JsonResponse([
                'sku' => $sku,
                'location_id' => $locationId ?? 'ALL',
                'stock' => $stock
            ], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }
}
