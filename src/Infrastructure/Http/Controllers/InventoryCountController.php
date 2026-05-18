<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use InventoryApp\Application\Inventory\UseCases\StartInventoryCount;
use InventoryApp\Application\Inventory\UseCases\RecordCountItem;
use InventoryApp\Application\Inventory\UseCases\CompleteInventoryCount;
use Illuminate\Support\Str;
use Exception;

class InventoryCountController
{
    public function start(Request $request, StartInventoryCount $useCase): JsonResponse
    {
        try {
            // Generate a UUID for the new count session
            $countId = Str::uuid()->toString();
            
            $useCase->execute($countId);

            return response()->json([
                'message' => 'Inventory count started successfully.',
                'count_id' => $countId
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function recordItem(string $countId, Request $request, RecordCountItem $useCase): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:0'
            ]);

            $useCase->execute($countId, $validated['sku'], $validated['quantity']);

            return response()->json(['message' => 'Item count recorded successfully.']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function complete(string $countId, CompleteInventoryCount $useCase): JsonResponse
    {
        try {
            $useCase->execute($countId);

            return response()->json(['message' => 'Inventory count completed and stock reconciled.']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
