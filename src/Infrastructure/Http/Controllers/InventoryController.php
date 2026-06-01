<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\TransferStock;
use InventoryApp\Application\Inventory\Queries\StockQueryServiceInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
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

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id']);
            $quantity = new Quantity($validated['quantity']);

            $useCase->execute($sku, $locationId, $quantity);

            return new Response(['message' => 'Stock received successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function dispatch(RequestInterface $request, DispatchStock $useCase)
    {
        try {
            $validated = $request->validate([
                'sku'         => 'required|string',
                'quantity'    => 'required|integer|min:1',
                'location_id' => 'required|string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id']);
            $quantity = new Quantity($validated['quantity']);

            $useCase->execute($sku, $locationId, $quantity);

            return new Response(['message' => 'Stock dispatched successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function transfer(RequestInterface $request, TransferStock $useCase)
    {
        try {
            $validated = $request->validate([
                'sku'             => 'required|string',
                'from_location'   => 'required|string',
                'to_location'     => 'required|string',
                'quantity'        => 'required|integer|min:1',
            ]);

            $sku = new SKU($validated['sku']);
            $fromLocation = new LocationId($validated['from_location']);
            $toLocation = new LocationId($validated['to_location']);
            $quantity = new Quantity($validated['quantity']);

            $useCase->execute(
                $sku,
                $fromLocation,
                $toLocation,
                $quantity
            );

            return new Response(['message' => 'Stock transferred successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function stockLevel(RequestInterface $request, string $sku, StockQueryServiceInterface $queryService)
    {
        try {
            $locationId = $request->query('location_id');
            $stockLevelDto = $queryService->getStockLevel($sku, $locationId);

            return new Response([
                'sku' => $stockLevelDto->sku,
                'location_id' => $stockLevelDto->locationId,
                'stock' => $stockLevelDto->stockQuantity
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 404);
        }
    }
}
