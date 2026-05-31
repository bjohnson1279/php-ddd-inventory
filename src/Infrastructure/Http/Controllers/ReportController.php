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
            // 1. Fetch all products for tenant
            $products = DB::table('products')
                ->where('tenant_id', $tenantId)
                ->get();

            $totalFifoCents = 0;
            $totalLifoCents = 0;
            $totalWacCents = 0;
            $totalItems = 0;
            $lowStockAlerts = 0;
            $lowStockItems = [];
            $locationValuations = [];

            // Fetch all locations to initialize location names
            $locations = DB::table('locations')->get()->keyBy('id')->toArray();

            foreach ($products as $p) {
                // Get stock at all locations for this product
                $stocks = DB::table('product_locations')
                    ->where('product_id', $p->id)
                    ->get();

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

                $totalItems += $totalStock;

                if ($totalStock <= (int)$p->reorder_threshold) {
                    $lowStockAlerts++;
                    $lowStockItems[] = [
                        'id'                => $p->id,
                        'name'              => $p->name,
                        'sku'               => $p->sku,
                        'current_stock'     => $totalStock,
                        'reorder_threshold' => (int)$p->reorder_threshold
                    ];
                }

                // Fetch active cost layers for this product variant
                $layers = DB::table('inventory_cost_layers')
                    ->where('tenant_id', $tenantId)
                    ->where('variant_id', $p->sku)
                    ->where('remaining_quantity', '>', 0)
                    ->get()
                    ->toArray();

                $totalLayersQty = array_sum(array_column($layers, 'remaining_quantity'));
                $totalLayersCost = array_sum(array_map(fn($l) => $l->remaining_quantity * $l->unit_cost_cents, $layers));

                // 1. Compute WAC (Weighted Average Cost)
                $wacUnitCents = 1000; // default $10.00
                if ($totalLayersQty > 0) {
                    $wacUnitCents = (int)($totalLayersCost / $totalLayersQty);
                } else {
                    // fallback to catalog price
                    $catalogVariant = DB::table('catalog_variants')->where('sku', $p->sku)->first();
                    if ($catalogVariant) {
                        $wacUnitCents = (int)($catalogVariant->price * 100);
                    }
                }

                $wacValuation = $totalStock * $wacUnitCents;
                $totalWacCents += $wacValuation;

                // Add to location valuation breakdown
                foreach ($stocks as $s) {
                    $locationValuations[$s->location_id]['valuation'] += ((int)$s->stock_quantity * $wacUnitCents);
                }

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
                $totalFifoCents += $fifoValuation;

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
                $totalLifoCents += $lifoValuation;
            }

            // Fetch recent transaction activity
            $transactions = DB::table('inventory_transactions')
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $activity = [];
            foreach ($transactions as $t) {
                $prod = DB::table('products')->where('id', $t->product_id)->first();
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

            return new Response([
                'total_valuation_fifo_cents' => $totalFifoCents,
                'total_valuation_lifo_cents' => $totalLifoCents,
                'total_valuation_wac_cents'  => $totalWacCents,
                'total_items_count'          => $totalItems,
                'low_stock_alerts_count'     => $lowStockAlerts,
                'low_stock_items'            => $lowStockItems,
                'valuation_by_location'      => array_values($locationValuations),
                'recent_activity'            => $activity
            ], 200);

        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
