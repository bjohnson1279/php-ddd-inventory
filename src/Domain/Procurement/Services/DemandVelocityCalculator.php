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
        int $windowDays = 30
    ): array {
        $sku = new SKU($skuStr);
        $product = $this->productRepo->findBySku($sku);
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
