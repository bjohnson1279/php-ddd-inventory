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

        $totalStock = (int) $query->sum('stock_quantity');
        $totalAllocated = (int) $query->sum('allocated_quantity');
        $totalInTransit = (int) $query->sum('in_transit_quantity');
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
