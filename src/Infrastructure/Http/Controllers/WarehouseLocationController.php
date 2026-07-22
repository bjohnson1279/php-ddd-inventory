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
                'maxVolumeCubicMeters'  => 'required',
                'gridX'                 => 'integer',
                'gridY'                 => 'integer',
                'width'                 => 'integer',
                'height'                => 'integer'
            ]);

            $location = $this->buildLocationFromRequest($request, $body);

            $repo->save($location);

            return new Response([
                'message' => 'Warehouse location saved successfully.',
                'location' => $this->formatLocation($location)
            ], 200);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function list(RequestInterface $request, WarehouseLocationRepositoryInterface $repo)
    {
        try {
            $locations = $repo->findAll();
            $data = array_map(function(WarehouseLocation $loc) {
                return $this->formatLocation($loc);
            }, $locations);

            return new Response($data, 200);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function delete(RequestInterface $request, string $id, WarehouseLocationRepositoryInterface $repo)
    {
        try {
            $repo->delete(new LocationId($id));
            return new Response(['message' => 'Warehouse location deleted successfully.'], 200);
        } catch (Exception $e) {
            return $this->handleException($e);
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
            return $this->handleException($e);
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
                throw new \InvalidArgumentException("Items array is required.");
            }

            $optimizer = new PickingRouteOptimizer($locationRepo);
            $optimized = $optimizer->optimizeRoute($items);

            return new Response($optimized, 200);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function suggestSlotting(RequestInterface $request)
    {
        try {
            $optimizer = new \InventoryApp\Domain\Inventory\Services\SlottingOptimizer();
            $suggestions = $optimizer->generateSuggestions();
            return new Response($suggestions, 200);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function handleException(Exception $e): Response
    {
        if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
            error_log('[WarehouseLocationController] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
        return new Response(['error' => $e->getMessage()], 400);
    }

    private function buildLocationFromRequest(RequestInterface $request, array $body): WarehouseLocation
    {
        $path = $request->query('path') ?? ($request->validate([])['path'] ?? null);

        $maxWeight = (int) $body['maxWeightGrams'];
        $maxVolume = (float) $body['maxVolumeCubicMeters'];

        $gridX  = isset($body['gridX']) ? (int) $body['gridX'] : 0;
        $gridY  = isset($body['gridY']) ? (int) $body['gridY'] : 0;
        $width  = isset($body['width']) ? (int) $body['width'] : 1;
        $height = isset($body['height']) ? (int) $body['height'] : 1;

        if ($path) {
            $parts = explode('-', $path);
            return new WarehouseLocation(
                new LocationId($path),
                $parts[0],
                $parts[1],
                $parts[2],
                $parts[3],
                $parts[4],
                $parts[5],
                $maxWeight,
                $maxVolume,
                $gridX,
                $gridY,
                $width,
                $height
            );
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
            return new WarehouseLocation(
                new LocationId($idStr),
                $bodyExtra['warehouseId'],
                $bodyExtra['zone'],
                $bodyExtra['aisle'],
                $bodyExtra['rack'],
                $bodyExtra['shelf'],
                $bodyExtra['bin'],
                $maxWeight,
                $maxVolume,
                $gridX,
                $gridY,
                $width,
                $height
            );
        }
    }

    private function formatLocation(WarehouseLocation $location): array
    {
        return [
            'id'                   => $location->getId()->getValue(),
            'warehouseId'          => $location->getWarehouseId(),
            'zone'                 => $location->getZone(),
            'aisle'                => $location->getAisle(),
            'rack'                 => $location->getRack(),
            'shelf'                => $location->getShelf(),
            'bin'                  => $location->getBin(),
            'maxWeightGrams'       => $location->getMaxWeightGrams(),
            'maxVolumeCubicMeters' => $location->getMaxVolumeCubicMeters(),
            'gridX'                => $location->getGridX(),
            'gridY'                => $location->getGridY(),
            'width'                => $location->getWidth(),
            'height'               => $location->getHeight()
        ];
    }
}
