<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\WarehouseLocationRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Infrastructure\Models\ProductLocationModel;
use Exception;

class PutawaySuggester
{
    private ProductRepositoryInterface $productRepo;
    private WarehouseLocationRepositoryInterface $locationRepo;

    public function __construct(
        ProductRepositoryInterface $productRepo,
        WarehouseLocationRepositoryInterface $locationRepo
    ) {
        $this->productRepo = $productRepo;
        $this->locationRepo = $locationRepo;
    }

    public function suggestPutaway(SKU $sku, int $quantity): array
    {
        if ($quantity <= 0) {
            throw new Exception("Quantity to put away must be positive.");
        }

        $product = $this->productRepo->findBySku($sku);
        if (!$product) {
            throw new Exception("Product variant with SKU {$sku->getValue()} not found.");
        }

        // Get attributes from catalog_variants
        $variantRow = Capsule::connection()->table('catalog_variants')->where('sku', $sku->getValue())->first();
        if (!$variantRow) {
            throw new Exception("Product variant with SKU {$sku->getValue()} not found.");
        }

        $attrs = json_decode($variantRow->attributes, true) ?: [];
        $tempZoneAttr = $attrs['temperatureZone'] ?? null;
        $hazardAttr = $attrs['hazardClass'] ?? null;
        $velocityAttr = $attrs['velocity'] ?? null;

        // Load all WMS locations
        $locations = $this->locationRepo->findAll();
        if (empty($locations)) {
            return [];
        }

        // Load all product locations to calculate current occupancy
        $allStocks = ProductLocationModel::where('stock_quantity', '>', 0)
            ->with('product')
            ->get();

        $occupiedMap = [];
        foreach ($allStocks as $stock) {
            if (!$stock->product) continue;
            $locId = $stock->location_id;
            
            $weight = $stock->stock_quantity * ($stock->product->weight_grams ?? 0);
            $volume = $stock->stock_quantity * ($stock->product->volume_cubic_meters ?? 0.0);

            if (!isset($occupiedMap[$locId])) {
                $occupiedMap[$locId] = ['weight' => 0, 'volume' => 0.0];
            }
            $occupiedMap[$locId]['weight'] += $weight;
            $occupiedMap[$locId]['volume'] += $volume;
        }

        $locationCapacities = [];
        foreach ($locations as $loc) {
            $locId = $loc->getPath();
            $occWeight = $occupiedMap[$locId]['weight'] ?? 0;
            $occVolume = $occupiedMap[$locId]['volume'] ?? 0.0;

            $remainingWeight = $loc->getMaxWeightGrams() - $occWeight;
            $remainingVolume = $loc->getMaxVolumeCubicMeters() - $occVolume;

            $locationCapacities[] = [
                'location' => $loc,
                'remainingWeight' => $remainingWeight,
                'remainingVolume' => $remainingVolume
            ];
        }

        // Score candidates
        $scoredCandidates = [];
        foreach ($locationCapacities as $c) {
            $score = 0;
            $matchesZoneType = true;
            $loc = $c['location'];
            $zoneLower = strtolower($loc->getZone());

            // 1. Temperature Zone
            if ($tempZoneAttr) {
                if ($zoneLower === strtolower($tempZoneAttr)) {
                    $score += 100;
                } else {
                    $matchesZoneType = false;
                }
            }

            // 2. Hazard Class
            if ($hazardAttr) {
                if ($zoneLower === 'hazmat') {
                    $score += 200;
                } else {
                    $matchesZoneType = false;
                }
            } else {
                if ($zoneLower === 'hazmat') {
                    $matchesZoneType = false;
                }
            }

            // 3. Velocity
            if ($velocityAttr && strtolower($velocityAttr) === 'fast-moving') {
                if ($zoneLower === 'fast') {
                    $score += 50;
                }
                $aisle = $loc->getAisle();
                if ($aisle === 'A01' || $aisle === 'A02' || $aisle === 'A03') {
                    $score += 30;
                }
            }

            $scoredCandidates[] = array_merge($c, [
                'score' => $score,
                'matchesZoneType' => $matchesZoneType
            ]);
        }

        // Filter eligible
        $eligible = array_filter($scoredCandidates, function($c) {
            return $c['matchesZoneType'] && $c['remainingWeight'] > 0 && $c['remainingVolume'] > 0.0;
        });

        // Sort
        usort($eligible, function($a, $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] - $a['score'];
            }
            return $b['remainingWeight'] - $a['remainingWeight'];
        });

        // Suggest allocation
        $recommendations = [];
        $remainingToAllocate = $quantity;
        $variantWeight = $product->getWeightGrams() ?? 0;
        $variantVolume = $product->getVolumeCubicMeters() ?? 0.0;

        foreach ($eligible as $cand) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            $maxWeightUnits = $variantWeight > 0 ? floor($cand['remainingWeight'] / $variantWeight) : INF;
            $maxVolumeUnits = $variantVolume > 0.0 ? floor($cand['remainingVolume'] / $variantVolume) : INF;
            
            $maxUnitsToFit = min($maxWeightUnits, $maxVolumeUnits);

            if ($maxUnitsToFit <= 0) {
                continue;
            }

            $allocatedQty = min($remainingToAllocate, $maxUnitsToFit);

            $allocatedWeight = $allocatedQty * $variantWeight;
            $allocatedVolume = $allocatedQty * $variantVolume;

            $recommendations[] = [
                'locationId' => $cand['location']->getPath(),
                'quantity' => (int) $allocatedQty,
                'remainingWeightGrams' => $cand['remainingWeight'] - $allocatedWeight,
                'remainingVolumeCubicMeters' => $cand['remainingVolume'] - $allocatedVolume
            ];

            $remainingToAllocate -= $allocatedQty;
        }

        if ($remainingToAllocate > 0) {
            throw new Exception("Insufficient warehouse capacity to put away the entire quantity of {$quantity} units for SKU {$sku->getValue()}.");
        }

        return $recommendations;
    }
}
