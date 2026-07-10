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
    {
        // ⚡ Bolt: Use injected product if provided to prevent N+1 redundant queries.
        $product = $product ?? $this->productRepo->findBySku($sku);
        if (!$product) {
            throw new \Exception("Product not found for SKU: " . $sku->getValue());
        }

        $now = new DateTimeImmutable();
        $ninetyDaysAgo = $now->modify('-90 days');
        $thirtyDaysAgo = $now->modify('-30 days');
        $sevenDaysAgo = $now->modify('-7 days');

        $entries = $this->ledgerRepo->entriesFor($sku->getValue(), $locationId->getValue());

        $history90d = array_filter($entries, function ($e) use ($ninetyDaysAgo) {
            return $e->occurredAt >= $ninetyDaysAgo &&
                $e->quantity < 0 &&
                ($e->reason === ReasonCode::Sale || $e->reason === ReasonCode::KitSale);
        });

        $history30d = array_filter($history90d, function ($e) use ($thirtyDaysAgo) {
            return $e->occurredAt >= $thirtyDaysAgo;
        });

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
            'sku' => $sku->getValue(),
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
        $velocity = $this->calculateSalesVelocity($sku, $locationId, $product);
        $baseQuantity = $velocity['averageDailySales30d'] * $forecastDays;
        $forecastedQuantity = (int) ceil($baseQuantity * $trendMultiplier);

        $periodStart = new DateTimeImmutable();
        $periodEnd = $periodStart->modify('+' . $forecastDays . ' days');

        $confidenceLevel = $velocity['averageDailySales30d'] > 0 ? 0.85 : 0.5;

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
