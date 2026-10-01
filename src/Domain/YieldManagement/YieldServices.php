<?php

namespace App\Domain\YieldManagement;

class YieldCalculationService {
    public function calculateOptimalPrice(InventoryYieldMetrics $metrics, LiquidationProfile $profile): int {
        $currentPrice = $profile->basePriceCents;
        
        $accruedHoldingCost = $metrics->daysInInventory * $profile->holdingCostPerDayCents;
        $currentPrice -= $accruedHoldingCost;
        
        if ($metrics->daysUntilExpiration !== null && $metrics->daysUntilExpiration < 14) {
            $expectedSales = $metrics->historicalDailyDemand * $metrics->daysUntilExpiration;
            if ($metrics->currentStockQuantity > $expectedSales) {
                $currentPrice = $profile->minFloorPriceCents;
            }
        }
        
        return max($currentPrice, $profile->minFloorPriceCents);
    }
}

class DynamicPricingEngine {
    private YieldCalculationService $calculationService;

    public function __construct(YieldCalculationService $calculationService) {
        $this->calculationService = $calculationService;
    }

    public function generateMarkdown(InventoryYieldMetrics $metrics, LiquidationProfile $profile): ?PriceMarkdownRecommendation {
        $optimalPrice = $this->calculationService->calculateOptimalPrice($metrics, $profile);
        
        if ($optimalPrice < $profile->basePriceCents) {
            $reasoning = "Accrued holding costs";
            if ($optimalPrice === $profile->minFloorPriceCents && $metrics->daysUntilExpiration !== null && $metrics->daysUntilExpiration < 14) {
                $reasoning = "Approaching Expiration - High Overstock";
            }
            
            return new PriceMarkdownRecommendation(
                uniqid(),
                $metrics->sku,
                $optimalPrice,
                $reasoning
            );
        }
        
        return null;
    }
}
