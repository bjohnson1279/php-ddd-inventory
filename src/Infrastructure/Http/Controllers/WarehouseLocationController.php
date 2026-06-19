<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Inventory\Repositories\WarehouseLocationRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\WarehouseLocation;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\Services\PutawaySuggester;
use InventoryApp\Domain\Inventory\Services\PickingRouteOptimizer;
use InventoryApp\Infrastructure\ServiceContainer;
use Exception;

class WarehouseLocationController
{
    public function save(RequestInterface $request, WarehouseLocationRepositoryInterface $repo)
    {
        try {
            $body = $request->validate([
                'maxWeightGrams'        => 'required|integer|min:1',
                'maxVolumeCubicMeters'  => 'required'
            ]);

            $path = $request->query('path') ?? ($request->validate([])['path'] ?? null);
            
            $maxWeight = (int) $body['maxWeightGrams'];
            $maxVolume = (float) $body['maxVolumeCubicMeters'];

            if ($path) {
                $location = WarehouseLocation::parsePath($path, $maxWeight, $maxVolume);
            } else {
                $bodyExtra = $request->validate([
                    'warehouseId' => 'required',
                    'zone'        => 'required',
                    'aisle'       => 'required',
                    'rack'        => 'required',
                    'shelf'       => 'required',
                    'bin'         => 'required'
                ]);

                $idStr = "{$bodyExtra['warehouseId']}-{$bodyExtra['zone']}-{$bodyExtra['aisle']}-{$bodyExtra['rack']}-{$bodyExtra['shelf']}-{$bodyExtra['bin']}";
                $location = new WarehouseLocation(
                    new LocationId($idStr),
                    $bodyExtra['warehouseId'],
                    $bodyExtra['zone'],
                    $bodyExtra['aisle'],
                    $bodyExtra['rack'],
                    $bodyExtra['shelf'],
                    $bodyExtra['bin'],
                    $maxWeight,
                    $maxVolume
                );
            }

            $repo->save($location);

            return new Response([
                'message' => 'Warehouse location saved successfully.',
                'location' => [
                    'id'                   => $location->getId()->getValue(),
                    'warehouseId'          => $location->getWarehouseId(),
                    'zone'                 => $location->getZone(),
                    'aisle'                => $location->getAisle(),
                    'rack'                 => $location->getRack(),
                    'shelf'                => $location->getShelf(),
                    'bin'                  => $location->getBin(),
                    'maxWeightGrams'       => $location->getMaxWeightGrams(),
                    'maxVolumeCubicMeters' => $location->getMaxVolumeCubicMeters()
                ]
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function list(RequestInterface $request, WarehouseLocationRepositoryInterface $repo)
    {
        try {
            $locations = $repo->findAll();
            $data = array_map(function(WarehouseLocation $loc) {
                return [
                    'id'                   => $loc->getId()->getValue(),
                    'warehouseId'          => $loc->getWarehouseId(),
                    'zone'                 => $loc->getZone(),
                    'aisle'                => $loc->getAisle(),
                    'rack'                 => $loc->getRack(),
                    'shelf'                => $loc->getShelf(),
                    'bin'                  => $loc->getBin(),
                    'maxWeightGrams'       => $loc->getMaxWeightGrams(),
                    'maxVolumeCubicMeters' => $loc->getMaxVolumeCubicMeters()
                ];
            }, $locations);

            return new Response($data, 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }

    public function delete(RequestInterface $request, string $id, WarehouseLocationRepositoryInterface $repo)
    {
        try {
            $repo->delete(new LocationId($id));
            return new Response(['message' => 'Warehouse location deleted successfully.'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function suggestPutaway(RequestInterface $request, ProductRepositoryInterface $productRepo, WarehouseLocationRepositoryInterface $locationRepo)
    {
        try {
            $body = $request->validate([
                'sku'      => 'required',
                'quantity' => 'required|integer|min:1'
            ]);

            $sku = new SKU($body['sku']);
            $qty = (int) $body['quantity'];

            $suggester = new PutawaySuggester($productRepo, $locationRepo);
            $suggestions = $suggester->suggestPutaway($sku, $qty);

            return new Response($suggestions, 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function optimizePickRoute(RequestInterface $request, WarehouseLocationRepositoryInterface $locationRepo)
    {
        try {
            $body = $request->validate([
                'items' => 'required'
            ]);

            $items = $body['items'];
            if (!is_array($items)) {
                throw new Exception("Items array is required.");
            }

            $optimizer = new PickingRouteOptimizer($locationRepo);
            $optimized = $optimizer->optimizeRoute($items);

            return new Response($optimized, 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
