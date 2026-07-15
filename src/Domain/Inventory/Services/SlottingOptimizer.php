<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Inventory\Services;

use Illuminate\Database\Capsule\Manager as Capsule;

class SlottingOptimizer
{
    public function generateSuggestions(): array
    {
        // 1. Fetch all locations
        $locations = Capsule::table('warehouse_locations')->get();
        if ($locations->isEmpty()) {
            return [];
        }

        // Map distances to (0,0)
        $locDistanceMap = [];
        foreach ($locations as $loc) {
            $dist = abs((int) ($loc->grid_x ?? 0)) + abs((int) ($loc->grid_y ?? 0));
            $locDistanceMap[$loc->id] = $dist;
        }

        // 2. Fetch all ledger entries in the last 30 days to calculate velocity
        $thirtyDaysAgo = (new \DateTime())->modify('-30 days')->format('Y-m-d H:i:s');
        $entries = Capsule::table('ledger_entries')
            ->where('occurred_at', '>=', $thirtyDaysAgo)
            ->where('quantity', '<', 0)
            ->get();

        $velocities = [];
        foreach ($entries as $e) {
            $metadata = json_decode($e->metadata ?? '{}', true);
            if (is_string($e->metadata)) {
                $metadata = json_decode($e->metadata, true) ?? [];
            } else if (is_array($e->metadata)) {
                $metadata = $e->metadata;
            }
            $locationId = $metadata['locationId'] ?? 'default';
            $sku = $e->variant_id; // variant_id holds the SKU string directly in php-ddd-inventory
            $key = $sku . '_' . $locationId;
            $velocities[$key] = ($velocities[$key] ?? 0) + abs((int) $e->quantity);
        }

        // 3. Fetch current inventory allocations
        $items = Capsule::table('product_locations')
            ->join('products', 'product_locations.product_id', '=', 'products.id')
            ->select('products.sku', 'product_locations.location_id')
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        // Map current items with their velocities and distances
        $itemRecords = [];
        foreach ($items as $item) {
            $key = $item->sku . '_' . $item->location_id;
            $velocity = $velocities[$key] ?? 0;
            $distance = $locDistanceMap[$item->location_id] ?? 9999;
            $itemRecords[] = [
                'sku' => $item->sku,
                'locationId' => $item->location_id,
                'velocity' => $velocity,
                'distance' => $distance
            ];
        }

        // Sort items by velocity descending
        usort($itemRecords, function ($a, $b) {
            return $b['velocity'] <=> $a['velocity'];
        });

        $suggestions = [];
        $matchedLocations = [];

        foreach ($itemRecords as $item) {
            if ($item['velocity'] === 0) {
                continue;
            }
            if (in_array($item['locationId'], $matchedLocations, true)) {
                continue;
            }

            // Find an occupied location with lower velocity that is closer to (0,0)
            $bestSwapTarget = null;
            $maxDistanceDiff = 0;

            foreach ($itemRecords as $target) {
                if ($target['locationId'] === $item['locationId']) {
                    continue;
                }
                if (in_array($target['locationId'], $matchedLocations, true)) {
                    continue;
                }

                if ($target['distance'] < $item['distance']) {
                    if ($target['velocity'] < $item['velocity']) {
                        $distanceDiff = $item['distance'] - $target['distance'];
                        if ($distanceDiff > $maxDistanceDiff) {
                            $maxDistanceDiff = $distanceDiff;
                            $bestSwapTarget = $target;
                        }
                    }
                }
            }

            if ($bestSwapTarget !== null) {
                $travelSavings = $item['velocity'] * $maxDistanceDiff * 2;

                $suggestions[] = [
                    'sku' => $item['sku'],
                    'currentLocationId' => $item['locationId'],
                    'currentDistance' => $item['distance'],
                    'currentVelocity' => $item['velocity'],
                    'recommendedLocationId' => $bestSwapTarget['locationId'],
                    'recommendedDistance' => $bestSwapTarget['distance'],
                    'potentialSwapSku' => $bestSwapTarget['sku'],
                    'estimatedSavings' => $travelSavings
                ];

                $matchedLocations[] = $item['locationId'];
                $matchedLocations[] = $bestSwapTarget['locationId'];
            }
        }

        // Sort suggestions by estimated savings descending
        usort($suggestions, function ($a, $b) {
            return $b['estimatedSavings'] <=> $a['estimatedSavings'];
        });

        return $suggestions;
    }
}
