<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\DemandForecastRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId;
use InventoryApp\Domain\Inventory\Entities\DemandForecast;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use DateTimeImmutable;

class DemandForecaster
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepo,
        private readonly LedgerRepositoryInterface $ledgerRepo,
        private readonly ReorderPolicyRepositoryInterface $replenishmentRuleRepo,
        private readonly DemandForecastRepositoryInterface $demandForecastRepo
    ) {}

    public function calculateSalesVelocity(SKU $sku, LocationId $locationId, ?Product $product = null): array
    public function calculateSalesVelocity(SKU $sku, LocationId $locationId, ?Product $product = null, ?array $entries = null): array
    {
        // ⚡ Bolt: Use injected product if provided to prevent N+1 redundant queries.
        $product = $product ?? $this->productRepo->findBySku($sku);
        if (!$product) {
            throw new \Exception("Product not found for SKU: " . $sku->getValue());
        }

        $entries = $this->ledgerRepo->entriesFor($sku->getValue(), $locationId->getValue());
        $entries = $entries ?? $this->ledgerRepo->entriesFor($sku->getValue(), $locationId->getValue());

        return $this->calculateSalesVelocityFromData($product, $locationId, $entries);
    }

    private function calculateSalesVelocityFromData($product, LocationId $locationId, array $entries): array
    {
        $skuStr = $product->getSku()->getValue();
        $now = new DateTimeImmutable();
        $ninetyDaysAgo = $now->modify('-90 days');
        $thirtyDaysAgo = $now->modify('-30 days');
        $sevenDaysAgo = $now->modify('-7 days');

        $history90d = array_filter($entries, function ($e) use ($ninetyDaysAgo) {
            return $e->occurredAt >= $ninetyDaysAgo &&
                $e->quantity < 0 &&
                ($e->reason === ReasonCode::Sale || $e->reason === ReasonCode::KitSale);
        });

        $history30d = array_filter($history90d, function ($e) use ($thirtyDaysAgo) {
            return $e->occurredAt >= $thirtyDaysAgo;

        $history7d = array_filter($history30d, function ($e) use ($sevenDaysAgo) {
            return $e->occurredAt >= $sevenDaysAgo;
        });


        $locationStock = $product->getStockAt($locationId);
        $currentStock = $locationStock->getStockQuantity()->getValue();

        $sum7d = array_reduce($history7d, fn($acc, $e) => $acc + abs($e->quantity), 0);
        $sum30d = array_reduce($history30d, fn($acc, $e) => $acc + abs($e->quantity), 0);
        $sum90d = array_reduce($history90d, fn($acc, $e) => $acc + abs($e->quantity), 0);

        $ads7d = (float) number_format($sum7d / 7, 3, '.', '');
        $ads30d = (float) number_format($sum30d / 30, 3, '.', '');
        $ads90d = (float) number_format($sum90d / 90, 3, '.', '');

        $daysOfCover = INF;
        $runOutDate = null;

        if ($ads30d > 0) {
            $daysOfCover = (int) ceil($currentStock / $ads30d);
            $runOutDate = $now->modify('+' . $daysOfCover . ' days');
        }

        return [
            'sku' => $skuStr,
            'locationId' => $locationId->getValue(),
            'currentStock' => $currentStock,
            'averageDailySales7d' => $ads7d,
            'averageDailySales30d' => $ads30d,
            'averageDailySales90d' => $ads90d,
            'daysOfCover' => $daysOfCover,
            'runOutDate' => $runOutDate
        ];
    }

    public function generateDemandForecast(
        SKU $sku,
        LocationId $locationId,
        int $forecastDays,
        float $trendMultiplier = 1.0,
        ?Product $product = null
    ): DemandForecast {
        $entries = $this->ledgerRepo->entriesFor($sku->getValue(), $locationId->getValue());
        $velocity = $this->calculateSalesVelocity($sku, $locationId, $product, $entries);
        $baseQuantity = $velocity['averageDailySales30d'] * $forecastDays;

        // --- Seasonal Multiplier Calculation ---
        $oneYearAgo = $now->modify('-365 days');

        $dispatches = array_filter($entries, function ($e) use ($oneYearAgo) {
            return $e->occurredAt >= $oneYearAgo &&
        $velocity = $this->calculateSalesVelocity($sku, $locationId, $product);

        $now = new DateTimeImmutable();

                $e->quantity < 0 &&
                ($e->reason === ReasonCode::Sale || $e->reason === ReasonCode::KitSale);
        });

        $seasonalMultiplier = 1.0;
        if (!empty($dispatches)) {
            $monthlySales = array_fill(0, 12, 0);
            foreach ($dispatches as $entry) {
                $month = (int) $entry->occurredAt->format('n') - 1;
                $monthlySales[$month] += abs($entry->quantity);
            }

            $totalSales = array_sum($monthlySales);
            $activeMonths = count(array_filter($monthlySales, fn($s) => $s > 0)) ?: 1;
            $overallMonthlyAverage = $totalSales / $activeMonths;

            if ($overallMonthlyAverage > 0) {
                $targetMonth = (int) (new \DateTime())->format('n') - 1;
                $targetMonthSales = $monthlySales[$targetMonth];
                if ($targetMonthSales > 0) {
                    $seasonalMultiplier = $targetMonthSales / $overallMonthlyAverage;
                    $seasonalMultiplier = max(0.3, min(3.0, $seasonalMultiplier));
                }
            }
        }

        $forecastedQuantity = (int) ceil($baseQuantity * $trendMultiplier * $seasonalMultiplier);

        $periodStart = new DateTimeImmutable();
        $periodEnd = $periodStart->modify('+' . $forecastDays . ' days');

        $confidenceLevel = $velocity['averageDailySales30d'] > 0 ? ($seasonalMultiplier != 1.0 ? 0.90 : 0.85) : 0.5;
        $confidenceLevel = $velocity['averageDailySales30d'] > 0 ? (abs($seasonalMultiplier - 1.0) > 1e-6 ? 0.90 : 0.85) : 0.5;

        $id = new DemandForecastId(\Ramsey\Uuid\Uuid::uuid4()->toString());

        $forecast = new DemandForecast(
            $id,
            $sku,
            $locationId,
            $forecastedQuantity,
            $periodStart,
            $periodEnd,
            $confidenceLevel,
            new DateTimeImmutable()
        );

        $this->demandForecastRepo->save($forecast);

        // Auto-trigger reorder policy evaluation on ROP breach
        try {
            $reorderPolicyService = \InventoryApp\Infrastructure\ServiceContainer::reorderPolicyService();
            $reorderPolicyService->checkPolicy(
                $sku->getValue(),
                $locationId->getValue(),
                $velocity['currentStock'],
                'default-tenant'
            );
        } catch (\Throwable $e) {
            error_log("Failed to evaluate policy inside demand forecaster: " . $e->getMessage());
        }

        return $forecast;
    }

    public function getDemandPlanningReport(LocationId $locationId): array
    {
        $stocks = \Illuminate\Database\Capsule\Manager::table('product_locations')
            ->join('products', 'product_locations.product_id', '=', 'products.id')
            ->where('product_locations.location_id', $locationId->getValue())
            ->select('products.sku')
            ->get();

        $skuStrings = $stocks->pluck('sku')->toArray();
        $skuObjects = array_map(fn($s) => new SKU($s), $skuStrings);

        $products = $this->productRepo->findBySkus($skuObjects);
        $forecasts = $this->demandForecastRepo->findAllForLocation($locationId);
        $policies = $this->replenishmentRuleRepo->findAllByLocation($locationId->getValue());
        $policyMap = [];
        foreach ($policies as $p) {
            $policyMap[$p->sku->getValue()] = $p;
        }

        // Batched lookups to prevent N+1
        $allEntries = $this->ledgerRepo->entriesForSkusAndLocation($skuStrings, $locationId->getValue());
        $entriesBySku = [];
        foreach ($allEntries as $entry) {
            $entriesBySku[$entry->variantId][] = $entry;
        }

        $policies = $this->replenishmentRuleRepo->findBySkusAndLocation($skuObjects, $locationId->getValue());
        $policyMap = [];
        foreach ($policies as $p) {
            $policyMap[$p->sku->getValue()] = $p;
        }

        $reportItems = [];
        foreach ($skuStrings as $skuStr) {
            $sku = new SKU($skuStr);
            $product = $products[$skuStr] ?? null;
            if (!$product) {
                continue;
            }

            // ⚡ Bolt: Pass the pre-fetched $product to prevent N+1 query inside calculateSalesVelocity.
            $velocity = $this->calculateSalesVelocity($sku, $locationId, $product);
            $policy = $this->replenishmentRuleRepo->findBySkuAndLocation($sku, $locationId->getValue());
            // This leverages the existing $entriesBySku and $policies pre-fetched above.
            $velocity = $this->calculateSalesVelocity($sku, $locationId, $product, $entriesBySku[$skuStr] ?? []);
            $policy = $policyMap[$skuStr] ?? null;
            // This leverages the existing $entriesBySku and $policies pre-fetched above.
            $policy = $policies[$skuStr] ?? null;

            $reorderPoint = $policy ? $policy->reorderPoint : 10;
            $reorderQuantity = $policy ? $policy->reorderQuantity : 20;
            $safetyStock = $policy ? $policy->safetyStock : 5;

            $now = new DateTimeImmutable();
            $endWindow = $now->modify('+30 days');

            $activeForecast = null;
            foreach ($forecasts as $f) {
                if ($f->sku->getValue() === $skuStr &&
                    $f->periodEnd >= $now &&
                    $f->periodStart <= $endWindow
                ) {
                    $activeForecast = $f;
                    break;
                }
            }

            $forecastedDemand30d = $activeForecast ? $activeForecast->forecastedQuantity : (int) ceil($velocity['averageDailySales30d'] * 30);
            $defaultConfidence = $velocity['averageDailySales30d'] > 0 ? 0.70 : 0.50;
            $confidenceLevel = $activeForecast ? $activeForecast->confidenceLevel : $defaultConfidence;

            $locationStock = $product->getStockAt($locationId);
            $currentStock = $locationStock->getStockQuantity()->getValue();

            $actionRequired = $currentStock <= $reorderPoint;
            $recommendedOrderQuantity = $actionRequired ? $reorderQuantity : 0;

            $reportItems[] = [
                'sku' => $skuStr,
                'locationId' => $locationId->getValue(),
                'currentStock' => $currentStock,
                'averageDailySales7d' => $velocity['averageDailySales7d'],
                'averageDailySales30d' => $velocity['averageDailySales30d'],
                'averageDailySales90d' => $velocity['averageDailySales90d'],
                'daysOfCover' => $velocity['daysOfCover'],
                'runOutDate' => $velocity['runOutDate'],

                'reorderPoint' => $reorderPoint,
                'reorderQuantity' => $reorderQuantity,
                'safetyStock' => $safetyStock,

                'forecastedDemand30d' => $forecastedDemand30d,
                'confidenceLevel' => $confidenceLevel,

                'actionRequired' => $actionRequired,
                'recommendedOrderQuantity' => $recommendedOrderQuantity
            ];
        }

        return $reportItems;
    }
}



{

    public function calculateSalesVelocity(SKU $sku, LocationId $locationId, ?Product $product = null): array
    {
        }


    }

    {








        }

    }

        $velocity = $this->calculateSalesVelocity($sku, $locationId, $product);



            }


                }
            }
        }







        }

    }

    {


        $policies = $this->replenishmentRuleRepo->findAllByLocation($locationId->getValue());
        }

        }


            }

            $velocity = $this->calculateSalesVelocity($sku, $locationId, $product);
            $policy = $this->replenishmentRuleRepo->findBySkuAndLocation($sku, $locationId->getValue());



                }
            }







        }

    }
}
