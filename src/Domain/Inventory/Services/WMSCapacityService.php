<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\WarehouseLocationRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\Exceptions\CapacityExceededException;
use InventoryApp\Infrastructure\Models\ProductLocationModel;

class WMSCapacityService
{
    private ProductRepositoryInterface $productRepo;
    private WarehouseLocationRepositoryInterface $locationRepo;

    public function __construct(
        ProductRepositoryInterface $productRepo,
        WarehouseLocationRepositoryInterface $locationRepo
    ) {
        $this->productRepo = $productRepo;
        $this->locationRepo = $locationRepo;
    }

    public function validateCapacity(string $locationIdStr, array $adjustments): void
    {
        $locationId = new LocationId($locationIdStr);
        $location = $this->locationRepo->findById($locationId);

        // If the location does not exist in the WMS repository, it is treated as unconstrained
        if (!$location) {
            return;
        }

        // Load current items in this location
        $currentItems = ProductLocationModel::where('location_id', $locationIdStr)
            ->where('stock_quantity', '>', 0)
            ->with('product')
            ->get();

        // Build map of SKU to quantity
        $quantityMap = [];
        foreach ($currentItems as $item) {
            if ($item->product) {
                $quantityMap[$item->product->sku] = $item->stock_quantity;
            }
        }

        // Apply adjustments
        foreach ($adjustments as $adj) {
            $sku = $adj['sku'];
            $mode = $adj['mode'];
            $qty = $adj['quantity'];

            if ($mode === 'absolute') {
                $quantityMap[$sku] = $qty;
            } else {
                $current = $quantityMap[$sku] ?? 0;
                $quantityMap[$sku] = $current + $qty;
            }
        }

        // Calculate total weight and volume
        $totalWeight = 0;
        $totalVolume = 0.0;

        $activeSkus = [];
        foreach ($quantityMap as $skuStr => $qty) {
            if ($qty > 0) {
                $activeSkus[] = new SKU($skuStr);
            }
        }

        if (empty($activeSkus)) {
            return;
        }

        // Fetch products by skus
        $products = $this->productRepo->findBySkus($activeSkus);

        foreach ($quantityMap as $skuStr => $qty) {
            if ($qty <= 0) {
                continue;
            }

            $product = $products[$skuStr] ?? null;
            if (!$product) {
                continue;
            }

            $totalWeight += $qty * ($product->getWeightGrams() ?? 0);
            $totalVolume += $qty * ($product->getVolumeCubicMeters() ?? 0.0);
        }

        // Enforce constraints
        if ($totalWeight > $location->getMaxWeightGrams()) {
            throw new CapacityExceededException(
                $locationIdStr,
                'weight',
                $location->getMaxWeightGrams(),
                $totalWeight
            );
        }

        if ($totalVolume > $location->getMaxVolumeCubicMeters()) {
            throw new CapacityExceededException(
                $locationIdStr,
                'volume',
                $location->getMaxVolumeCubicMeters(),
                $totalVolume
            );
        }
    }
}
