<?php

namespace InventoryApp\Domain\Procurement\Services;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;

class DemandVelocityCalculator
{
    public function __construct(
        private readonly LedgerRepositoryInterface $ledgerRepo,
        private readonly ProductRepositoryInterface $productRepo
    ) {}

    public function calculateDailySalesStats(
        string $skuStr,
        string $locationId,
        int $windowDays = 30
    ): array {
        $sku = new SKU($skuStr);
        $product = $this->productRepo->findBySku($sku);
        if (!$product) {
            return ['average' => 0.0, 'stdDev' => 0.0];
        }

        $entries = $this->ledgerRepo->entriesFor($product->getId(), $locationId);

        $now = new \DateTime();
        $startDate = new \DateTime();
        $startDate->modify("-{$windowDays} days");
        $startDate->setTime(0, 0, 0);

        $salesEntries = array_filter($entries, function ($e) use ($startDate) {
            return (
                $e->occurredAt >= $startDate &&
                $e->quantity < 0 &&
                ($e->reason === ReasonCode::Sale || $e->reason === ReasonCode::KitSale)
            );
        });

        $totalQuantity = array_reduce($salesEntries, function ($sum, $e) {
            return $sum + abs($e->quantity);
        }, 0);

        $average = $totalQuantity / $windowDays;

        $dailyQuantities = array_fill(0, $windowDays, 0);
        $todayClean = new \DateTime();
        $todayClean->setTime(23, 59, 59);

        foreach ($salesEntries as $entry) {
            $diffSeconds = $todayClean->getTimestamp() - $entry->occurredAt->getTimestamp();
            $dayOffset = (int) floor($diffSeconds / (24 * 60 * 60));
            $dayIndex = $windowDays - 1 - $dayOffset;
            if ($dayIndex >= 0 && $dayIndex < $windowDays) {
                $dailyQuantities[$dayIndex] += abs($entry->quantity);
            }
        }

        $varianceSum = array_reduce($dailyQuantities, function ($sum, $qty) use ($average) {
            return $sum + pow($qty - $average, 2);
        }, 0.0);

        $stdDev = sqrt($varianceSum / $windowDays);

        return ['average' => $average, 'stdDev' => $stdDev];
    }
}

class ReorderPointForecaster
{
    public function __construct(
        private readonly DemandVelocityCalculator $velocityCalculator,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly PurchaseOrderRepositoryInterface $poRepo
    ) {}

    public function forecastReorderPoint(
        string $skuStr,
        string $locationId,
        int $leadTimeDays,
        int $safetyStock,
        int $windowDays,
        ?string $tenantId = null
    ): int {
        $stats = $this->velocityCalculator->calculateDailySalesStats($skuStr, $locationId, $windowDays);
        $meanSales = $stats['average'];
        $stdDevSales = $stats['stdDev'];

        $leadTimeDaysAvg = $leadTimeDays;
        $leadTimeDaysStdDev = 0.0;

        if ($tenantId !== null) {
            $sku = new SKU($skuStr);
            $product = $this->productRepo->findBySku($sku);
            if ($product) {
                $allPos = $this->poRepo->findAll();
                $receivedPos = [];

                foreach ($allPos as $po) {
                    if ($po->tenantId !== $tenantId || $po->getStatus() !== PurchaseOrderStatus::Received) {
                        continue;
                    }

                    $getLocIdStr = fn($loc) => is_string($loc) ? $loc : ($loc ? $loc->getValue() : '');
                    $ruleLocIdStr = $getLocIdStr($locationId);
                    $poLocIdStr = $getLocIdStr($po->locationId);

                    // Filter received POs containing this variant at this location
                    $hasItem = false;
                    foreach ($po->getItems() as $item) {
                        if ($item->variantId === $product->getId()) {
                            $hasItem = true;
                            break;
                        }
                    }

                    if ($poLocIdStr === $ruleLocIdStr && $hasItem) {
                        $receivedPos[] = $po;
                    }
                }

                // Fallback: search across all locations for this tenant if none at destination location
                if (empty($receivedPos)) {
                    foreach ($allPos as $po) {
                        if ($po->tenantId !== $tenantId || $po->getStatus() !== PurchaseOrderStatus::Received) {
                            continue;
                        }

                        $hasItem = false;
                        foreach ($po->getItems() as $item) {
                            if ($item->variantId === $product->getId()) {
                                $hasItem = true;
                                break;
                            }
                        }

                        if ($hasItem) {
                            $receivedPos[] = $po;
                        }
                    }
                }

                if (!empty($receivedPos)) {
                    $leadTimes = [];
                    foreach ($receivedPos as $po) {
                        if ($po->createdAt !== null && $po->updatedAt !== null) {
                            $diffSeconds = $po->updatedAt->getTimestamp() - $po->createdAt->getTimestamp();
                            $leadTimes[] = max(0.0, $diffSeconds / (24 * 60 * 60));
                        } else {
                            $leadTimes[] = (double) $leadTimeDays;
                        }
                    }

                    $totalLT = array_sum($leadTimes);
                    $leadTimeDaysAvg = $totalLT / count($leadTimes);

                    $ltVarianceSum = array_reduce($leadTimes, function ($sum, $lt) use ($leadTimeDaysAvg) {
                        return $sum + pow($lt - $leadTimeDaysAvg, 2);
                    }, 0.0);
                    $leadTimeDaysStdDev = sqrt($ltVarianceSum / count($leadTimes));
                }
            }
        }

        $zScore = 1.65;
        $term1 = $leadTimeDaysAvg * pow($stdDevSales, 2);
        $term2 = pow($meanSales, 2) * pow($leadTimeDaysStdDev, 2);
        $calculatedSafetyStock = $zScore * sqrt($term1 + $term2);

        $finalSafetyStock = $calculatedSafetyStock > 0 ? $calculatedSafetyStock : $safetyStock;
        $rawRop = $meanSales * $leadTimeDaysAvg + $finalSafetyStock;

        return (int) ceil($rawRop);
    }
}
