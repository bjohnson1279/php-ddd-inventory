<?php

namespace InventoryApp\Infrastructure\Persistence\Queries;

use InventoryApp\Application\Inventory\Queries\StockQueryServiceInterface;
use InventoryApp\Application\Inventory\Queries\StockLevelDTO;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class EloquentStockQueryService implements StockQueryServiceInterface
{
    public function __construct(private readonly string $tenantId) {}

    public function getStockLevel(string $sku, ?string $locationId = null): StockLevelDTO
    {
        $product = DB::table('products')
            ->where('tenant_id', $this->tenantId)
            ->where('sku', $sku)
            ->first(['id', 'sku']);

        if (!$product) {
            throw new Exception("Product not found with SKU: {$sku}");
        }

        $query = DB::table('product_locations')
            ->where('product_id', $product->id);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        // Bolt optimization: Replace three separate aggregate queries with a single query
        // Expected Impact: Reduces database round-trips by 66% when querying stock levels
        $totals = (clone $query)->selectRaw('
            COALESCE(SUM(stock_quantity), 0) as total_stock,
            COALESCE(SUM(allocated_quantity), 0) as total_allocated,
            COALESCE(SUM(in_transit_quantity), 0) as total_in_transit
        ')->first();

        $totalStock = (int) $totals->total_stock;
        $totalAllocated = (int) $totals->total_allocated;
        $totalInTransit = (int) $totals->total_in_transit;
        $available = $totalStock - $totalAllocated + $totalInTransit;
        if ($available < 0) {
            $available = 0;
        }

        return new StockLevelDTO(
            $product->sku,
            $locationId ?? 'ALL',
            $totalStock,
            $totalAllocated,
            $totalInTransit,
            $available
        );
    }
}
