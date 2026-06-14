<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class ReportController
{
    public function valuation(RequestInterface $request, string $tenantId)
    {
        try {
            $authUserTenantId = $_SERVER['auth.tenant_id'] ?? null;
            if ($authUserTenantId === null || ($authUserTenantId !== 'system' && $authUserTenantId !== $tenantId)) {
                return new Response(['error' => 'Unauthorized access to tenant report'], 403);
            }

            // 1. Fetch all products for tenant
            $products = DB::table('products')->where('tenant_id', $tenantId)->get();
            $productIds = $products->pluck('id')->toArray();
            $productSkus = $products->pluck('sku')->toArray();

            // Fetch all locations to initialize location names
            $locations = DB::table('locations')->get()->keyBy('id')->toArray();

            // Fetch ALL stock locations for these products once (to avoid N+1)
            $allStocks = $this->fetchStocksMap($productIds);

            // Fetch ALL active cost layers for these products once
            $allLayers = $this->fetchCostLayersMap($tenantId, $productSkus);

            // Fetch ALL catalog variants for these products once
            $catalogVariants = $this->fetchCatalogVariantsMap($productSkus);

            $reportData = $this->buildReportData($products, $locations, $allStocks, $allLayers, $catalogVariants);
            $reportData['recent_activity'] = $this->getRecentActivity($tenantId);

            return new Response($reportData, 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function fetchStocksMap(array $productIds): \Illuminate\Support\Collection
    {
        if (empty($productIds)) {
            return collect([]);
        }
        return DB::table('product_locations')
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');
    }

    private function fetchCostLayersMap(string $tenantId, array $productSkus): \Illuminate\Support\Collection
    {
        if (empty($productSkus)) {
            return collect([]);
        }
        return DB::table('inventory_cost_layers')
            ->where('tenant_id', $tenantId)
            ->whereIn('variant_id', $productSkus)
            ->where('remaining_quantity', '>', 0)
            ->get()
            ->groupBy('variant_id');
    }

    private function fetchCatalogVariantsMap(array $productSkus): \Illuminate\Support\Collection
    {
        if (empty($productSkus)) {
            return collect([]);
        }
        return DB::table('catalog_variants')
            ->whereIn('sku', $productSkus)
            ->get()
            ->keyBy('sku');
    }

    private function buildReportData(
        \Illuminate\Support\Collection $products,
        array $locations,
        \Illuminate\Support\Collection $allStocks,
        \Illuminate\Support\Collection $allLayers,
        \Illuminate\Support\Collection $catalogVariants
    ): array {
        $data = [
            'total_valuation_fifo_cents' => 0,
            'total_valuation_lifo_cents' => 0,
            'total_valuation_wac_cents'  => 0,
            'total_items_count'          => 0,
            'low_stock_alerts_count'     => 0,
            'low_stock_items'            => [],
            'valuation_by_location'      => []
        ];

        $locationValuations = [];

        foreach ($products as $p) {
            // Get stock at all locations for this product
            $stocks = $allStocks->get($p->id, collect([]));
            $totalStock = $this->processProductStocks($stocks, $locations, $locationValuations);
            $data['total_items_count'] += $totalStock;

            $this->checkLowStockAlerts($p, $totalStock, $data['low_stock_alerts_count'], $data['low_stock_items']);

            // Fetch active cost layers for this product variant
            $layers = $allLayers->get($p->sku, collect([]))->toArray();
            $wacUnitCents = $this->calculateWacUnitCents($layers, $p->sku, $catalogVariants);

            $data['total_valuation_wac_cents'] += ($totalStock * $wacUnitCents);
            $this->addLocationValuations($stocks, $wacUnitCents, $locationValuations);

            $data['total_valuation_fifo_cents'] += $this->calculateFifoValuation($layers, $totalStock, $wacUnitCents);
            $data['total_valuation_lifo_cents'] += $this->calculateLifoValuation($layers, $totalStock, $wacUnitCents);
        }

        $data['valuation_by_location'] = array_values($locationValuations);
        return $data;
    }

    private function processProductStocks(iterable $stocks, array $locations, array &$locationValuations): int
    {
        $totalStock = 0;
        foreach ($stocks as $s) {
            $totalStock += (int)$s->stock_quantity;

            // Initialize location valuation tracking
            if (!isset($locationValuations[$s->location_id])) {
                $locName = $locations[$s->location_id]->name ?? $s->location_id;
                $locationValuations[$s->location_id] = [
                    'location_id' => $s->location_id,
                    'name'        => $locName,
                    'valuation'   => 0
                ];
            }
        }
        return $totalStock;
    }

    private function checkLowStockAlerts(object $product, int $totalStock, int &$lowStockAlerts, array &$lowStockItems): void
    {
        if ($totalStock <= (int)$product->reorder_threshold) {
            $lowStockAlerts++;
            $lowStockItems[] = [
                'id'                => $product->id,
                'name'              => $product->name,
                'sku'               => $product->sku,
                'current_stock'     => $totalStock,
                'reorder_threshold' => (int)$product->reorder_threshold
            ];
        }
    }

    private function calculateWacUnitCents(array $layers, string $sku, $catalogVariants = null): int
    {
        $totalLayersQty = array_sum(array_column($layers, 'remaining_quantity'));
        $totalLayersCost = array_sum(array_map(fn($l) => $l->remaining_quantity * $l->unit_cost_cents, $layers));

        // 1. Compute WAC (Weighted Average Cost)
        $wacUnitCents = 1000; // default $10.00
        if ($totalLayersQty > 0) {
            $wacUnitCents = (int)($totalLayersCost / $totalLayersQty);
        } else {
            // fallback to catalog price
            if ($catalogVariants !== null) {
                $catalogVariant = $catalogVariants->get($sku);
            } else {
                $catalogVariant = DB::table('catalog_variants')->where('sku', $sku)->first();
            }
            if ($catalogVariant) {
                $wacUnitCents = (int)($catalogVariant->price * 100);
            }
        }

        return $wacUnitCents;
    }

    private function addLocationValuations(iterable $stocks, int $wacUnitCents, array &$locationValuations): void
    {
        // Add to location valuation breakdown
        foreach ($stocks as $s) {
            $locationValuations[$s->location_id]['valuation'] += ((int)$s->stock_quantity * $wacUnitCents);
        }
    }

    private function calculateFifoValuation(array $layers, int $totalStock, int $wacUnitCents): int
    {
        // 2. Compute FIFO Valuation (valuation of remaining inventory)
        // Since FIFO consumes the oldest first, the remaining stock belongs to the newest layers.
        // Sort layers by received_at DESC (newest first)
        usort($layers, fn($a, $b) => strcmp($b->received_at, $a->received_at));

        $remainingToVal = $totalStock;
        $fifoValuation = 0;
        foreach ($layers as $l) {
            if ($remainingToVal <= 0) break;
            $qtyToTake = min($remainingToVal, (int)$l->remaining_quantity);
            $fifoValuation += $qtyToTake * (int)$l->unit_cost_cents;
            $remainingToVal -= $qtyToTake;
        }
        // If remaining stock exceeds cost layers, value the rest at average cost
        if ($remainingToVal > 0) {
            $fifoValuation += $remainingToVal * $wacUnitCents;
        }
        return $fifoValuation;
    }

    private function calculateLifoValuation(array $layers, int $totalStock, int $wacUnitCents): int
    {
        // 3. Compute LIFO Valuation (valuation of remaining inventory)
        // Since LIFO consumes the newest first, the remaining stock belongs to the oldest layers.
        // Sort layers by received_at ASC (oldest first)
        usort($layers, fn($a, $b) => strcmp($a->received_at, $b->received_at));

        $remainingToVal = $totalStock;
        $lifoValuation = 0;
        foreach ($layers as $l) {
            if ($remainingToVal <= 0) break;
            $qtyToTake = min($remainingToVal, (int)$l->remaining_quantity);
            $lifoValuation += $qtyToTake * (int)$l->unit_cost_cents;
            $remainingToVal -= $qtyToTake;
        }
        if ($remainingToVal > 0) {
            $lifoValuation += $remainingToVal * $wacUnitCents;
        }
        return $lifoValuation;
    }

    private function getRecentActivity(string $tenantId): array
    {
        // Fetch recent transaction activity
        $transactions = DB::table('inventory_transactions')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($transactions->isEmpty()) {
            return [];
        }

        // Bolt optimization: Extract product fetching out of the loop.
        // Instead of executing `DB::table('products')->where('id', ...)->first()` 5 times,
        // we extract the unique product IDs and perform a single O(1) batched query.
        // This solves an N+1 query issue for the activity feed.
        $productIds = $transactions->pluck('product_id')->unique()->toArray();
        $products = DB::table('products')->whereIn('id', $productIds)->get()->keyBy('id');

        $activity = [];
        foreach ($transactions as $t) {
            $prod = $products->get($t->product_id);
            $activity[] = [
                'id'              => $t->id,
                'product_name'    => $prod ? $prod->name : $t->product_id,
                'sku'             => $prod ? $prod->sku : '',
                'type'            => $t->type,
                'quantity_change' => (int)$t->quantity_change,
                'condition'       => $t->condition,
                'created_at'      => $t->created_at
            ];
        }

        return $activity;
    }
}
