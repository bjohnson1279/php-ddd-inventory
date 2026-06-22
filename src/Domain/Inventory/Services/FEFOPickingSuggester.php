<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Exception;

class FEFOPickingSuggester
{
    public function __construct(
        private readonly CostLayerRepositoryInterface $costLayers,
        private readonly LedgerRepositoryInterface $ledgerRepo,
        private readonly ProductRepositoryInterface $productRepo
    ) {}

    public function suggestFefoPicking(string $skuStr, int $quantity): array
    {
        if ($quantity <= 0) {
            throw new Exception("Pick quantity must be positive.");
        }

        $product = $this->productRepo->findBySku(new SKU($skuStr));
        if (!$product) {
            throw new Exception("Product variant with SKU {$skuStr} not found.");
        }

        $variantId = $skuStr;

        // 1. Get active cost layers sorted by expiration date ascending (FEFO)
        $activeLayers = $this->costLayers->getActiveLayers($variantId, 'expiration_date ASC');
        $lotLayers = array_filter($activeLayers, fn($l) => !empty($l->lotNumber));

        if (empty($lotLayers)) {
            throw new Exception("No lot-controlled inventory layers found for SKU {$skuStr}.");
        }

        // 2. Fetch all ledger entries for this variant to compute physical location-lot balances
        $ledgerEntries = $this->ledgerRepo->entriesFor($variantId);

        // Map: lotNumber -> Map: locationId -> netQuantity
        $lotBalances = [];

        foreach ($ledgerEntries as $entry) {
            $lotNo = $entry->metadata['lotNumber'] ?? null;
            if (!$lotNo) {
                continue;
            }

            $locId = $entry->metadata['locationId'] ?? 'default';
            if (!isset($lotBalances[$lotNo])) {
                $lotBalances[$lotNo] = [];
            }
            if (!isset($lotBalances[$lotNo][$locId])) {
                $lotBalances[$lotNo][$locId] = 0;
            }
            $lotBalances[$lotNo][$locId] += $entry->quantity;
        }

        // 3. Fulfill pick quantity using earliest expiring lots first
        $suggestions = [];
        $remainingToPick = $quantity;

        foreach ($lotLayers as $layer) {
            if ($remainingToPick <= 0) {
                break;
            }

            $lotNo = $layer->lotNumber;
            if (!isset($lotBalances[$lotNo])) {
                continue;
            }

            // Iterate through locations holding this lot and allocate quantity
            foreach ($lotBalances[$lotNo] as $locationId => $locationQty) {
                if ($locationQty <= 0) {
                    continue;
                }
                if ($remainingToPick <= 0) {
                    break;
                }

                $allocatedFromLocation = min($remainingToPick, $locationQty);

                // Deduct from temporary balance tracking
                $lotBalances[$lotNo][$locationId] -= $allocatedFromLocation;
                $remainingToPick -= $allocatedFromLocation;

                $suggestions[] = [
                    'locationId'     => $locationId,
                    'lotNumber'      => $lotNo,
                    'expirationDate' => $layer->expirationDate?->format('Y-m-d H:i:s'),
                    'quantity'       => $allocatedFromLocation,
                ];
            }
        }

        if ($remainingToPick > 0) {
            throw new Exception("Insufficient lot-controlled inventory available to pick {$quantity} units for SKU {$skuStr} (Missing: {$remainingToPick}).");
        }

        return $suggestions;
    }
}
