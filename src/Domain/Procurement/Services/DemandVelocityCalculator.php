<?php

namespace InventoryApp\Domain\Procurement\Services;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
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
        int $windowDays = 30,
        ?\InventoryApp\Domain\Inventory\Entities\Product $product = null
    ): array {
        $sku = new SKU($skuStr);
        $product = $product ?? $this->productRepo->findBySku($sku);
        if (!$product) {
            return ['average' => 0.0, 'stdDev' => 0.0];
        }

        $entries = $this->ledgerRepo->entriesFor($product->getId(), $locationId);

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

        // ⚡ Bolt Optimization: Replaced array_reduce with a foreach loop to eliminate closure invocation overhead.
        $totalQuantity = 0;
        foreach ($salesEntries as $e) {
            $totalQuantity += abs($e->quantity);
        }

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

        // ⚡ Bolt Optimization: Replaced array_reduce with a foreach loop to eliminate closure invocation overhead.
        $varianceSum = 0.0;
        foreach ($dailyQuantities as $qty) {
            $varianceSum += pow($qty - $average, 2);
        }

        $stdDev = sqrt($varianceSum / $windowDays);

        return ['average' => $average, 'stdDev' => $stdDev];
    }
}
