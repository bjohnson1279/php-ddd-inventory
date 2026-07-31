<?php

namespace InventoryApp\Application\AI;

use Illuminate\Database\Capsule\Manager as DB;

class RebalanceOptimizationService
{
    private string $sidecarUrl;

    public function __construct()
    {
        $this->sidecarUrl = getenv('PYTHON_SIDECAR_URL') ?: 'http://localhost:5005';
    }

    public function getMatrix(string $tenantId): array
    {
        // 1. Fetch warehouse locations and derive unique warehouses
        $locations = DB::table('warehouse_locations')->get()->toArray();
        $warehouseMap = [];
        $locToWarehouse = [];
        foreach ($locations as $loc) {
            $whId = $loc->warehouse_id ?? 'default';
            $locToWarehouse[$loc->id] = $whId;
            if (!isset($warehouseMap[$whId])) {
                $warehouseMap[$whId] = [
                    'id' => $whId,
                    'name' => "Warehouse {$whId}",
                    'region' => $loc->zone ?? 'Default'
                ];
            }
        }
        $warehouses = array_values($warehouseMap);

        if (count($warehouses) <= 1) {
            return $this->fallback();
        }

        // 2. Fetch inventory and aggregate by SKU × warehouse
        $inventory = DB::table('inventory_items')->get()->toArray();
        $stockAgg = [];
        foreach ($inventory as $item) {
            $whId = $locToWarehouse[$item->location_id] ?? 'unknown';
            $key = $item->sku . '__' . $whId;
            if (!isset($stockAgg[$key])) {
                $stockAgg[$key] = ['on_hand' => 0, 'allocated' => 0, 'in_transit' => 0];
            }
            $stockAgg[$key]['on_hand'] += $item->quantity ?? 0;
            $stockAgg[$key]['allocated'] += $item->allocated ?? 0;
            $stockAgg[$key]['in_transit'] += $item->in_transit ?? 0;
        }

        $stockLevels = [];
        foreach ($stockAgg as $key => $val) {
            [$sku, $whId] = explode('__', $key);
            $stockLevels[] = [
                'sku' => $sku,
                'warehouse_id' => $whId,
                'on_hand' => $val['on_hand'],
                'allocated' => $val['allocated'],
                'in_transit' => $val['in_transit'],
                'safety_stock' => 0,
            ];
        }

        // 3. Build default lead times and shipping costs between warehouse pairs
        $leadTimes = [];
        $shippingCosts = [];
        foreach ($warehouses as $w1) {
            foreach ($warehouses as $w2) {
                if ($w1['id'] !== $w2['id']) {
                    $leadTimes[] = ['source_warehouse_id' => $w1['id'], 'dest_warehouse_id' => $w2['id'], 'transit_days' => 3];
                    $shippingCosts[] = ['source_warehouse_id' => $w1['id'], 'dest_warehouse_id' => $w2['id'], 'cost_per_unit' => 1.5];
                }
            }
        }

        $payload = json_encode([
            'warehouses' => $warehouses,
            'stock_levels' => $stockLevels,
            'demand_forecasts' => [],
            'lead_times' => $leadTimes,
            'shipping_costs' => $shippingCosts,
            'constraints' => [
                'max_transfers_per_run' => 20,
                'min_transfer_quantity' => 5,
                'min_days_of_cover_target' => 14.0,
            ],
        ]);

        // 4. POST to Python sidecar
        $url = $this->sidecarUrl . '/rebalance-optimize';
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            return $this->fallback();
        }

        $decoded = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->fallback();
        }

        // 5. Map snake_case response to camelCase
        return [
            'recommendations' => array_map(function ($r) {
                return [
                    'sku' => $r['sku'] ?? '',
                    'sourceWarehouseId' => $r['source_warehouse_id'] ?? '',
                    'destWarehouseId' => $r['dest_warehouse_id'] ?? '',
                    'quantity' => $r['quantity'] ?? 0,
                    'priority' => $r['priority'] ?? 'LOW',
                    'estimatedShippingCost' => $r['estimated_shipping_cost'] ?? 0,
                    'sourceCurrentDoc' => $r['source_current_doc'] ?? 0,
                    'destCurrentDoc' => $r['dest_current_doc'] ?? 0,
                    'sourceProjectedDoc' => $r['source_projected_doc'] ?? 0,
                    'destProjectedDoc' => $r['dest_projected_doc'] ?? 0,
                    'urgencyReason' => $r['urgency_reason'] ?? '',
                ];
            }, $decoded['recommendations'] ?? []),
            'matrix' => $decoded['matrix'] ?? [],
            'summary' => [
                'totalTransfers' => $decoded['summary']['total_transfers'] ?? 0,
                'totalCost' => $decoded['summary']['total_cost'] ?? 0,
                'skusImproved' => $decoded['summary']['skus_improved'] ?? 0,
                'avgDocImprovement' => $decoded['summary']['avg_doc_improvement'] ?? 0,
            ],
        ];
    }

    private function fallback(): array
    {
        return [
            'recommendations' => [],
            'matrix' => [],
            'summary' => [
                'totalTransfers' => 0,
                'totalCost' => 0,
                'skusImproved' => 0,
                'avgDocImprovement' => 0,
            ],
        ];
    }
}
