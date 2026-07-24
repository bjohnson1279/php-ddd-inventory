<?php

namespace App\Domain\CRDT;

class CRDTStockResolver
{
    public static function mergeCounters(array $counterA, array $counterB): array
    {
        $mergedIncrements = [];
        $mergedDecrements = [];

        $nodes = array_unique(array_merge(
            array_keys($counterA['increments'] ?? []),
            array_keys($counterB['increments'] ?? [])
        ));

        foreach ($nodes as $node) {
            $valA = $counterA['increments'][$node] ?? 0;
            $valB = $counterB['increments'][$node] ?? 0;
            $mergedIncrements[$node] = max($valA, $valB);
        }

        $decNodes = array_unique(array_merge(
            array_keys($counterA['decrements'] ?? []),
            array_keys($counterB['decrements'] ?? [])
        ));

        foreach ($decNodes as $node) {
            $valA = $counterA['decrements'][$node] ?? 0;
            $valB = $counterB['decrements'][$node] ?? 0;
            $mergedDecrements[$node] = max($valA, $valB);
        }

        return [
            'sku' => $counterA['sku'] ?? $counterB['sku'] ?? 'UNKNOWN',
            'increments' => $mergedIncrements,
            'decrements' => $mergedDecrements,
        ];
    }

    public static function calculateValue(array $counter): int
    {
        $inc = array_sum($counter['increments'] ?? []);
        $dec = array_sum($counter['decrements'] ?? []);
        return max(0, $inc - $dec);
    }
}
