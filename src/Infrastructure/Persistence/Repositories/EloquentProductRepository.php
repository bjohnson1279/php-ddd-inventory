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
use InventoryApp\Domain\Inventory\Exceptions\ConcurrencyException;
use Illuminate\Database\Capsule\Manager as DB;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly string $tenantId) {}

    public function getTenantId(): string { return $this->tenantId; }

    public function findById(string $id): ?Product
    {
        $model = ProductModel::with('locations')
            ->where('tenant_id', $this->tenantId)
            ->find($id);

        return $model ? $this->hydrate($model) : null;
    }

    public function findBySku(SKU $sku): ?Product
    {
        $model = ProductModel::with('locations')
            ->where('tenant_id', $this->tenantId)
            ->where('sku', $sku->getValue())
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function save(Product $product): void
    {
        $existing = ProductModel::where('id', $product->getId())->first();

        if ($existing) {
            $updated = ProductModel::where('id', $product->getId())
                ->where('version_id', $product->getVersionId())
                ->update([
                    'tenant_id'         => $this->tenantId,
                    'sku'               => $product->getSku()->getValue(),
                    'name'              => $product->getName(),
                    'department'        => $product->getDepartment()->getValue(),
                    'reorder_threshold' => $product->getReorderThreshold()->getValue(),
                    'version_id'        => $product->getVersionId() + 1,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);

            if ($updated === 0) {
                throw new ConcurrencyException("Concurrency error: The product has been modified by another process.");
            }
        } else {
            ProductModel::create([
                'id'                => $product->getId(),
                'tenant_id'         => $this->tenantId,
                'sku'               => $product->getSku()->getValue(),
                'name'              => $product->getName(),
                'department'        => $product->getDepartment()->getValue(),
                'reorder_threshold' => $product->getReorderThreshold()->getValue(),
                'version_id'        => $product->getVersionId() + 1,
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        $product->incrementVersion();

        foreach ($product->getLocationStocks() as $locationStock) {
            ProductLocationModel::updateOrCreate(
                [
                    'product_id'  => $product->getId(),
                    'location_id' => $locationStock->getLocationId()->getValue(),
                ],
                [
                    'stock_quantity'     => $locationStock->getStockQuantity()->getValue(),
                    'open_box_quantity'  => $locationStock->getOpenBoxQuantity()->getValue(),
                    'damaged_quantity'   => $locationStock->getDamagedQuantity()->getValue(),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]
            );
        }

        foreach ($product->getPendingTransactions() as $transaction) {
            InventoryTransactionModel::create([
                'tenant_id'       => $this->tenantId,
                'product_id'      => $transaction->getProductId(),
                'type'            => $transaction->getType()->getValue(),
                'quantity_change' => $transaction->getQuantityChange(),
                'condition'       => $transaction->getCondition()->getValue(),
                'created_at'      => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
                'reference_id'    => $transaction->getReference(),
            ]);
        }

        $product->clearPendingTransactions();
    }

    public function delete(Product $product): void
    {
        ProductModel::where('tenant_id', $this->tenantId)
            ->where('id', $product->getId())
            ->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function hydrate(ProductModel $model): Product
    {
        $product = new Product(
            $model->id,
            new SKU($model->sku),
            $model->name,
            new Department($model->department),
            new Quantity($model->reorder_threshold),
            $model->version_id ?? 1
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
}
