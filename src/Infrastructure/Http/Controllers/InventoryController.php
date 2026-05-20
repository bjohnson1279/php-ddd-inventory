<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use Exception;

class InventoryController // extends Controller
{
    public function receive(RequestInterface $request, ReceiveStock $useCase)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'location_id' => 'required|string'
            ]);

            $useCase->execute($validated['sku'], $validated['location_id'], $validated['quantity']);

            return new Response(['message' => 'Stock received successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function dispatch(RequestInterface $request, DispatchStock $useCase)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'location_id' => 'required|string'
            ]);

            $useCase->execute($validated['sku'], $validated['location_id'], $validated['quantity']);

            return new Response(['message' => 'Stock dispatched successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function stockLevel(RequestInterface $request, string $sku, GetStockLevel $useCase)
    {
        try {
            $locationId = $request->query('location_id');
            $stock = $useCase->execute($sku, $locationId);

            return new Response([
                'sku' => $sku,
                'location_id' => $locationId ?? 'ALL',
                'stock' => $stock
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 404);
        }
    }
}
