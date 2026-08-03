<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\WarehouseLocationRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\Exceptions\CapacityExceededException;
use InventoryApp\Infrastructure\Models\ProductLocationModel;
use Illuminate\Database\Capsule\Manager as Capsule;

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

        // Offload base calculation to the database to avoid memory exhaustion
        $totals = Capsule::table('product_locations')
            ->join('products', 'product_locations.product_id', '=', 'products.id')
            ->where('product_locations.location_id', $locationIdStr)
            ->where('product_locations.stock_quantity', '>', 0)
            ->select(
                Capsule::raw('SUM(product_locations.stock_quantity * products.weight_grams) as total_weight'),
                Capsule::raw('SUM(product_locations.stock_quantity * products.volume_cubic_meters) as total_volume')
            )
            ->first();

        $totalWeight = (int) ($totals->total_weight ?? 0);
        $totalVolume = (float) ($totals->total_volume ?? 0.0);

        if (!empty($adjustments)) {
            $adjustedSkus = array_unique(array_column($adjustments, 'sku'));
            $activeSkus = array_map(fn($sku) => new SKU($sku), $adjustedSkus);

            $products = $this->productRepo->findBySkus($activeSkus);

            $currentStock = Capsule::table('product_locations')
                ->join('products', 'product_locations.product_id', '=', 'products.id')
                ->where('product_locations.location_id', $locationIdStr)
                ->whereIn('products.sku', $adjustedSkus)
                ->pluck('product_locations.stock_quantity', 'products.sku')
                ->toArray();

            $quantityMap = [];
            foreach ($adjustedSkus as $sku) {
                $quantityMap[$sku] = $currentStock[$sku] ?? 0;
            }

            $newQuantityMap = $quantityMap;
            foreach ($adjustments as $adj) {
                $sku = $adj['sku'];
                $mode = $adj['mode'];
                $qty = $adj['quantity'];

                if ($mode === 'absolute') {
                    $newQuantityMap[$sku] = $qty;
                } else {
                    $newQuantityMap[$sku] += $qty;
                }
            }

            foreach ($adjustedSkus as $sku) {
                $oldQty = max(0, $quantityMap[$sku]);
                $newQty = max(0, $newQuantityMap[$sku]);
                $netChange = $newQty - $oldQty;

                if ($netChange !== 0 && isset($products[$sku])) {
                    $product = $products[$sku];
                    $totalWeight += $netChange * ($product->getWeightGrams() ?? 0);
                    $totalVolume += $netChange * ($product->getVolumeCubicMeters() ?? 0.0);
                }
            }
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
