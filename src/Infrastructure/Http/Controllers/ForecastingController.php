<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Inventory\Services\DemandForecaster;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Repositories\DemandForecastRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use Exception;

class ForecastingController
{
    public function getReport(
        RequestInterface $request,
        ProductRepositoryInterface $productRepo,
        LedgerRepositoryInterface $ledgerRepo,
        ReorderPolicyRepositoryInterface $replenishmentRuleRepo,
        DemandForecastRepositoryInterface $demandForecastRepo
    ) {
        try {
            $locationIdStr = $request->query('locationId', 'default');
            $locationId = new LocationId($locationIdStr);

            $forecaster = new DemandForecaster(
                $productRepo,
                $ledgerRepo,
                $replenishmentRuleRepo,
                $demandForecastRepo
            );

            $report = $forecaster->getDemandPlanningReport($locationId);

            // Format DateTimeImmutable objects to ISO-8601 strings and format daysOfCover
            $formattedReport = [];
            foreach ($report as $item) {
                $formattedReport[] = [
                    'sku'                       => $item['sku'],
                    'locationId'                => $item['locationId'],
                    'currentStock'              => $item['currentStock'],
                    'averageDailySales7d'       => $item['averageDailySales7d'],
                    'averageDailySales30d'      => $item['averageDailySales30d'],
                    'averageDailySales90d'      => $item['averageDailySales90d'],
                    'daysOfCover'               => is_infinite($item['daysOfCover']) ? null : $item['daysOfCover'],
                    'runOutDate'                => $item['runOutDate'] ? $item['runOutDate']->format('Y-m-d\TH:i:s\Z') : null,
                    'reorderPoint'              => $item['reorderPoint'],
                    'reorderQuantity'           => $item['reorderQuantity'],
                    'safetyStock'               => $item['safetyStock'],
                    'forecastedDemand30d'       => $item['forecastedDemand30d'],
                    'confidenceLevel'           => $item['confidenceLevel'],
                    'actionRequired'            => $item['actionRequired'],
                    'recommendedOrderQuantity'  => $item['recommendedOrderQuantity'],
                ];
            }

            return new Response($formattedReport, 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ForecastingController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function generateForecast(
        RequestInterface $request,
        ProductRepositoryInterface $productRepo,
        LedgerRepositoryInterface $ledgerRepo,
        ReorderPolicyRepositoryInterface $replenishmentRuleRepo,
        DemandForecastRepositoryInterface $demandForecastRepo
    ) {
        try {
            $body = $request->validate([
                'sku'             => 'required|string',
                'locationId'      => 'string',
                'forecastDays'    => 'integer',
                'trendMultiplier' => 'numeric',
            ]);

            $sku = new SKU($body['sku']);
            $locationId = new LocationId($body['locationId'] ?? 'default');
            $forecastDays = isset($body['forecastDays']) ? (int) $body['forecastDays'] : 30;
            $trendMultiplier = isset($body['trendMultiplier']) ? (float) $body['trendMultiplier'] : 1.0;

            $forecaster = new DemandForecaster(
                $productRepo,
                $ledgerRepo,
                $replenishmentRuleRepo,
                $demandForecastRepo
            );

            $forecast = $forecaster->generateDemandForecast($sku, $locationId, $forecastDays, $trendMultiplier);

            return new Response([
                'message' => 'Demand forecast generated successfully',
                'forecast' => [
                    'id'                 => $forecast->id->getValue(),
                    'sku'                => $forecast->sku->getValue(),
                    'locationId'         => $forecast->locationId->getValue(),
                    'forecastedQuantity' => $forecast->forecastedQuantity,
                    'periodStart'        => $forecast->periodStart->format('Y-m-d\TH:i:s\Z'),
                    'periodEnd'          => $forecast->periodEnd->format('Y-m-d\TH:i:s\Z'),
                    'confidenceLevel'    => $forecast->confidenceLevel,
                    'createdAt'          => $forecast->createdAt->format('Y-m-d\TH:i:s\Z'),
                ]
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ForecastingController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function getStockVelocityReport(RequestInterface $request)
    {
        try {
            $variantId = $request->query('variantId');
            if (!$variantId) {
                return new Response(['error' => 'Missing required parameter: variantId'], 400);
            }

            $results = \Illuminate\Database\Capsule\Manager::select("
                SELECT bucket::text, units_dispatched as \"unitsDispatched\", units_received as \"unitsReceived\", transaction_count as \"transactionCount\"
                FROM stock_velocity_report
                WHERE variant_id = :variant_id
                ORDER BY bucket DESC
            ", ['variant_id' => $variantId]);

            return new Response($results, 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ForecastingController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
            return new Response(['error' => 'Failed to fetch stock velocity: ' . $e->getMessage()], 500);
        }
    }
}
