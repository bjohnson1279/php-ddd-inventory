<?php

namespace InventoryApp\Domain\Shipping\Services;

use InventoryApp\Domain\Shipping\ValueObjects\GeoLocation;
use InventoryApp\Domain\Shipping\Strategies\FulfillmentPlan;
use InventoryApp\Domain\Shipping\Strategies\FulfillmentAllocation;
use InventoryApp\Domain\Shipping\Strategies\RoutingStrategyInterface;
use Exception;

class OrderRoutingEngine
{
    /**
     * Evaluates all potential fulfillment plans and returns the optimal one.
     *
     * @param array{locationId: string, availableQuantity: int, geoLocation: GeoLocation}[] $candidates
     * @param callable(string, string, int): int $rateCalculator
     */
    public static function routeOrder(
        string $sku,
        int $quantity,
        GeoLocation $destination,
        array $candidates,
        RoutingStrategyInterface $strategy,
        callable $rateCalculator
    ): FulfillmentPlan {
        $activeCandidates = array_values(array_filter($candidates, function ($c) {
            return $c['availableQuantity'] > 0;
        }));

        $totalAvailable = array_reduce($activeCandidates, function ($sum, $c) {
            return $sum + $c['availableQuantity'];
        }, 0);

        if ($totalAvailable < $quantity) {
            throw new Exception("Insufficient total stock for SKU {$sku}. Requested: {$quantity}, Available: {$totalAvailable}");
        }

        $rawPlans = self::generatePlans($activeCandidates, $quantity);

        if (empty($rawPlans)) {
            throw new Exception("Could not find any valid allocation combinations for quantity {$quantity}");
        }

        $plans = [];
        foreach ($rawPlans as $allocations) {
            $totalDistance = 0.0;
            $totalCost = 0;

            foreach ($allocations as $alloc) {
                $candidate = null;
                foreach ($activeCandidates as $c) {
                    if ($c['locationId'] === $alloc->locationId) {
                        $candidate = $c;
                        break;
                    }
                }

                $dist = $candidate['geoLocation']->distanceTo($destination);
                $totalDistance += $dist;

                $rate = $rateCalculator($alloc->locationId, $sku, $alloc->quantity);
                $totalCost += $rate;
            }

            $splitCount = count($allocations) - 1;

            $plan = new FulfillmentPlan(
                $allocations,
                $totalCost,
                $totalDistance,
                $splitCount
            );

            $plan->score = $strategy->score($plan);
            $plans[] = $plan;
        }

        usort($plans, function ($a, $b) {
            return $a->score <=> $b->score;
        });

        return $plans[0];
    }

    /**
     * @return FulfillmentAllocation[][]
     */
    private static function generatePlans(array $candidates, int $quantity): array
    {
        $results = [];

        $recurse = function ($index, $remaining, $current) use (&$results, &$recurse, $candidates) {
            if ($remaining === 0) {
                $results[] = $current;
                return;
            }
            if ($index >= count($candidates)) {
                return;
            }

            $candidate = $candidates[$index];
            $allocQty = min($remaining, $candidate['availableQuantity']);

            if ($allocQty > 0) {
                $next = $current;
                $next[] = new FulfillmentAllocation($candidate['locationId'], $allocQty);
                $recurse($index + 1, $remaining - $allocQty, $next);
            }

            $recurse($index + 1, $remaining, $current);
        };

        $recurse(0, $quantity, []);
        return $results;
    }
}
