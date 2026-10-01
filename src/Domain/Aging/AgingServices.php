<?php

namespace App\Domain\Aging;

class InventoryAgingService {
    public function calculateAgingBuckets(
        string $sku,
        string $locationId,
        string $tenantId,
        \DateTimeImmutable $currentDate,
        array $ledgerEntries
    ): array {
        $lastReceiptDate = null;

        foreach ($ledgerEntries as $entry) {
            $qty = $entry['quantity'] ?? 0;
            if ($qty > 0) {
                // Ensure date is parsed correctly
                $dateVal = $entry['occurred_at'] ?? $entry['occurredAt'];
                $entryDate = is_string($dateVal) ? new \DateTimeImmutable($dateVal) : $dateVal;
                
                if (!$lastReceiptDate || $entryDate > $lastReceiptDate) {
                    $lastReceiptDate = $entryDate;
                }
            }
        }

        $daysOld = 0;
        if ($lastReceiptDate) {
            $diff = $currentDate->diff($lastReceiptDate);
            $daysOld = $diff->days;
        }

        $bucket = AgingBucket::DAYS_0_30;
        if ($daysOld > 180) {
            $bucket = AgingBucket::OVER_180_DAYS;
        } elseif ($daysOld > 90) {
            $bucket = AgingBucket::DAYS_91_180;
        } elseif ($daysOld > 60) {
            $bucket = AgingBucket::DAYS_61_90;
        } elseif ($daysOld > 30) {
            $bucket = AgingBucket::DAYS_31_60;
        }

        return [
            'daysSinceLastMovement' => max($daysOld, 0),
            'bucket' => $bucket
        ];
    }
}

class DeadStockRecommendationEngine {
    public function analyze(
        string $sku,
        string $locationId,
        string $tenantId,
        int $currentQuantity,
        int $unitCostCents,
        int $daysSinceLastMovement,
        string $agingBucket,
        bool $hasOverstock
    ): DeadStockAnalysis {
        $isDeadStock = $daysSinceLastMovement > 180;
        $action = RecommendedAction::NONE;

        if ($isDeadStock) {
            $action = $currentQuantity > 10 ? RecommendedAction::LIQUIDATE : RecommendedAction::DONATE;
        } elseif ($agingBucket === AgingBucket::DAYS_91_180 && $hasOverstock) {
            $action = RecommendedAction::MARKDOWN;
        }

        $lockedCapital = $currentQuantity * $unitCostCents;

        return new DeadStockAnalysis(
            $sku,
            $locationId,
            $tenantId,
            $currentQuantity,
            $daysSinceLastMovement,
            $isDeadStock,
            $agingBucket,
            $lockedCapital,
            $action
        );
    }
}

class EsgEmissionsCalculator {
    public function calculateScrapEmissions(string $sku, int $quantity, float $factorPerUnitKg): float {
        return $quantity * $factorPerUnitKg;
    }
}
