<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Inventory\UseCases\StartInventoryCount;
use InventoryApp\Application\Inventory\UseCases\RecordCountItem;
use InventoryApp\Application\Inventory\UseCases\CompleteInventoryCount;
use Illuminate\Support\Str;
use Exception;

class InventoryCountController
{
    public function start($request, StartInventoryCount $useCase): Response
    {
        try {
            // Generate a UUID for the new count session
            $countId = (string) Str::uuid();
            
            $useCase->execute($countId);

            return new Response([
                'message' => 'Inventory count started successfully.',
                'count_id' => $countId
            ], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function recordItem(string $countId, $request, RecordCountItem $useCase): Response
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'quantity' => 'required|integer'
            ]);

            $useCase->execute($countId, $validated['sku'], $validated['quantity']);

            return new Response(['message' => 'Item count recorded successfully.']);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function complete(string $countId, CompleteInventoryCount $useCase): Response
    {
        try {
            $useCase->execute($countId);

            return new Response(['message' => 'Inventory count completed and stock reconciled.']);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
