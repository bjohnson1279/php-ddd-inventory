<?php

namespace InventoryApp\Domain\Procurement\Services;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;

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
        ?string $tenantId = null,
        ?array $allPos = null,
        ?\InventoryApp\Domain\Inventory\Entities\Product $product = null
    ): int {
        $stats = $this->velocityCalculator->calculateDailySalesStats($skuStr, $locationId, $windowDays);
        $meanSales = $stats['average'];
        $stdDevSales = $stats['stdDev'];

        $leadTimeDaysAvg = $leadTimeDays;
        $leadTimeDaysStdDev = 0.0;

        if ($tenantId !== null) {
            $sku = new SKU($skuStr);
            $product = $product ?? $this->productRepo->findBySku($sku);
            if ($product) {
                $allPos = $allPos ?? $this->poRepo->findAll();
                $receivedPos = [];

                foreach ($allPos as $po) {
                    if ($po->tenantId !== $tenantId || $po->getStatus() !== PurchaseOrderStatus::Received) {
                        continue;
                    }

                    $getLocIdStr = fn($loc) => is_string($loc) ? $loc : ($loc ? $loc->getValue() : '');
                    $ruleLocIdStr = $getLocIdStr($locationId);
                    $poLocIdStr = $getLocIdStr($po->locationId);

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
