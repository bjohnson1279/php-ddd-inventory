<?php

namespace App\Domain\CycleCount;

class CycleCountExecutionService
{
    /**
     * @param CycleCountLineItem[] $items
     * @param array<string, int> $submittedCounts
     * @param float $varianceThresholdPct
     * @return bool True if all matched, False if recount required
     */
    public function processSubmission(array $items, array $submittedCounts, float $varianceThresholdPct = 0.05): bool
    {
        $requiresRecount = false;

        foreach ($items as $item) {
            $sku = $item->getSku();
            if (array_key_exists($sku, $submittedCounts)) {
                $counted = $submittedCounts[$sku];
                $success = $item->recordCount($counted, $varianceThresholdPct);
                if (!$success) {
                    $requiresRecount = true;
                }
            }
        }

        return !$requiresRecount;
    }
}
