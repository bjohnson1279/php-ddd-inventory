<?php

namespace App\Domain\CycleCount;

class ABCClassificationService
{
    public function classifySku(string $sku, float $annualUsageValue): string
    {
        if ($annualUsageValue > 10000) return 'A';
        if ($annualUsageValue > 1000) return 'B';
        return 'C';
    }

    public function getRecommendedFrequency(string $abcClass): int
    {
        return match ($abcClass) {
            'A' => 30,
            'B' => 90,
            'C' => 180,
            default => 365,
        };
    }
}
