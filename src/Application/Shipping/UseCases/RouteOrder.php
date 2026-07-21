<?php

namespace InventoryApp\Application\Shipping\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Shipping\ValueObjects\GeoLocation;
use InventoryApp\Domain\Shipping\Services\OrderRoutingEngine;
use InventoryApp\Domain\Shipping\Strategies\FulfillmentPlan;
use InventoryApp\Domain\Shipping\Strategies\MinimizeCostStrategy;
use InventoryApp\Domain\Shipping\Strategies\MinimizeSplitsStrategy;
use InventoryApp\Domain\Shipping\Strategies\MinimizeDistanceStrategy;
use InvalidArgumentException;
use Exception;

class RouteOrder
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CarrierServiceInterface $carrierService
    ) {}

    public function execute(string $sku, int $quantity, string $destinationAddress, ?string $strategyName = null): FulfillmentPlan
    {
        if (empty($sku) || $quantity <= 0 || empty($destinationAddress)) {
            throw new InvalidArgumentException("Missing or invalid required parameters: sku, quantity, and destinationAddress.");
        }

        $strategy = new MinimizeCostStrategy();
        if ($strategyName === 'MINIMIZE_SPLITS') {
            $strategy = new MinimizeSplitsStrategy();
        } elseif ($strategyName === 'MINIMIZE_DISTANCE') {
            $strategy = new MinimizeDistanceStrategy();
        }

        $destinationGeo = $this->geocodeAddress($destinationAddress);

        $skuObj = new SKU($sku);
        $product = $this->productRepository->findBySku($skuObj);

        if (!$product) {
            throw new Exception("Product with SKU {$sku} not found.");
        }

        $candidates = [];
        foreach ($product->getLocationStocks() as $locationId => $locationStock) {
            $candidates[] = [
                'locationId' => $locationId,
                'availableQuantity' => $locationStock->getAvailableQuantity()->getValue(),
                'geoLocation' => $this->getWarehouseGeoLocation($locationId)
            ];
        }

        usort($candidates, function ($a, $b) use ($destinationGeo) {
            return $a['geoLocation']->distanceTo($destinationGeo) <=> $b['geoLocation']->distanceTo($destinationGeo);
        });

        $bestPlan = OrderRoutingEngine::routeOrder(
            $sku,
            $quantity,
            $destinationGeo,
            $candidates,
            $strategy,
            function (string $locationId, string $productSku, int $qty) use ($destinationAddress) {
                try {
                    $rates = $this->carrierService->fetchRates($productSku, $qty, $destinationAddress, $locationId);
                    if (empty($rates)) {
                        return 999999;
                    }
                    $costs = array_map(fn($r) => $r->rateCents, $rates);
                    return min($costs);
                } catch (Exception $e) {
                    return 999999;
                }
            }
        );

        return $bestPlan;
    }

    private function geocodeAddress(string $address): GeoLocation
    {
        $normalized = strtolower($address);
        if (str_contains($normalized, "new york") || str_contains($normalized, "ny") || str_contains($normalized, "10001")) {
            return new GeoLocation(40.7128, -74.0060);
        }
        if (str_contains($normalized, "los angeles") || str_contains($normalized, "ca") || str_contains($normalized, "90210")) {
            return new GeoLocation(34.0522, -118.2437);
        }
        if (str_contains($normalized, "chicago") || str_contains($normalized, "il") || str_contains($normalized, "60601")) {
            return new GeoLocation(41.8781, -87.6298);
        }
        if (str_contains($normalized, "dallas") || str_contains($normalized, "tx") || str_contains($normalized, "75001")) {
            return new GeoLocation(32.7767, -96.7970);
        }

        $hash = crc32($address);
        
        $lat = 25.0 + abs($hash % 24);
        $lon = -125.0 + abs($hash % 58);
        return new GeoLocation($lat, $lon);
    }

    private function getWarehouseGeoLocation(string $locationId): GeoLocation
    {
        $loc = strtoupper($locationId);
        if (str_contains($loc, "EAST") || str_contains($loc, "WH1") || str_contains($loc, "NY")) {
            return new GeoLocation(40.7306, -73.9352);
        }
        if (str_contains($loc, "WEST") || str_contains($loc, "WH2") || str_contains($loc, "LA")) {
        }
        if (str_contains($loc, "CENTRAL") || str_contains($loc, "WH3") || str_contains($loc, "CH")) {
        }
        return new GeoLocation(39.8283, -98.5795);
    }
}



{

    {
        }

        }



        }

        }


                    }
                }
            }

    }

    {
        }
        }
        }
        }

        $hash = 0;
        for ($i = 0; $i < strlen($address); $i++) {
            $hash = ord($address[$i]) + (($hash << 5) - $hash);
        }
        
    }

    {
        }
        }
        }
    }
}
