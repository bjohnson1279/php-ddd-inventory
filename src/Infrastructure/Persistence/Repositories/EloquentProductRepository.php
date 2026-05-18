<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Entities\LocationStock;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Infrastructure\Models\ProductModel;
use InventoryApp\Infrastructure\Models\ProductLocationModel;
use InventoryApp\Infrastructure\Models\InventoryTransactionModel;
use Illuminate\Support\Facades\DB;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(string $id): ?Product
    {
        $model = ProductModel::with('locations')->find($id);
        if (!$model) return null;

        $product = new Product(
            $model->id,
            new SKU($model->sku),
            $model->name,
            new Department($model->department),
            new Quantity($model->reorder_threshold)
        );

        foreach ($model->locations as $locModel) {
            $product->loadLocationStock(new LocationStock(
                new LocationId($locModel->location_id),
                new Quantity($locModel->stock_quantity),
                new Quantity($locModel->open_box_quantity),
                new Quantity($locModel->damaged_quantity)
            ));
        }
        return $product;
    }
    
    public function findBySku(SKU $sku): ?Product
    {
        $model = ProductModel::with('locations')->where('sku', $sku->getValue())->first();
        if (!$model) return null;

        $product = new Product(
            $model->id,
            new SKU($model->sku),
            $model->name,
            new Department($model->department),
            new Quantity($model->reorder_threshold)
        );

        foreach ($model->locations as $locModel) {
            $product->loadLocationStock(new LocationStock(
                new LocationId($locModel->location_id),
                new Quantity($locModel->stock_quantity),
                new Quantity($locModel->open_box_quantity),
                new Quantity($locModel->damaged_quantity)
            ));
        }
        return $product;
    }
    
    public function save(Product $product): void
    {
        ProductModel::updateOrCreate(
            ['id' => $product->getId()],
            [
                'sku' => $product->getSku()->getValue(),
                'name' => $product->getName(),
                'department' => $product->getDepartment()->getValue(),
                'reorder_threshold' => $product->getReorderThreshold()->getValue(),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
        
        // Save locations
        foreach ($product->getLocationStocks() as $locationStock) {
            ProductLocationModel::updateOrCreate(
                [
                    'product_id' => $product->getId(),
                    'location_id' => $locationStock->getLocationId()->getValue()
                ],
                [
                    'stock_quantity' => $locationStock->getStockQuantity()->getValue(),
                    'open_box_quantity' => $locationStock->getOpenBoxQuantity()->getValue(),
                    'damaged_quantity' => $locationStock->getDamagedQuantity()->getValue(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        // Save pending transactions (Ledger)
        foreach ($product->getPendingTransactions() as $transaction) {
            InventoryTransactionModel::create([
                'product_id' => $transaction->getProductId(),
                'type' => $transaction->getType()->getValue(),
                'quantity_change' => $transaction->getQuantityChange(),
                'condition' => $transaction->getCondition()->getValue(),
                'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
                'reference_id' => $transaction->getReference(),
            ]);
        }

        $product->clearPendingTransactions();
    }
    
    public function delete(Product $product): void
    {
        ProductModel::where('id', $product->getId())->delete();
    }
}
