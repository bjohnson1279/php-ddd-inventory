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

    public function findBySkus(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        $skuValues = array_map(fn(SKU $sku) => $sku->getValue(), $skus);

        $models = ProductModel::with('locations')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('sku', $skuValues)
            ->get();

        $products = [];
        foreach ($models as $model) {
            $product = $this->hydrate($model);
            $products[$product->getSku()->getValue()] = $product;
        }

        return $products;
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

        $pendingTransactions = $product->getPendingTransactions();
        if (!empty($pendingTransactions)) {
            $transactionData = [];
            foreach ($pendingTransactions as $transaction) {
                $transactionData[] = [
                    'id'              => $transaction->getId(),
                    'tenant_id'       => $this->tenantId,
                    'product_id'      => $transaction->getProductId(),
                    'type'            => $transaction->getType()->getValue(),
                    'quantity_change' => $transaction->getQuantityChange(),
                    'condition'       => $transaction->getCondition()->getValue(),
                    'created_at'      => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
                    'reference_id'    => $transaction->getReference(),
                ];
            }
            InventoryTransactionModel::insert($transactionData);
        }

        $product->clearPendingTransactions();
    }

    public function saveAll(array $products): void
    {
        if (empty($products)) {
            return;
        }

        DB::transaction(function () use ($products) {
            $productData = [];
            $locationData = [];
            $transactionData = [];

            $now = date('Y-m-d H:i:s');

            foreach ($products as $product) {
                $productData[] = [
                    'id'                => $product->getId(),
                    'tenant_id'         => $this->tenantId,
                    'sku'               => $product->getSku()->getValue(),
                    'name'              => $product->getName(),
                    'department'        => $product->getDepartment()->getValue(),
                    'reorder_threshold' => $product->getReorderThreshold()->getValue(),
                    'updated_at'        => $now,
                ];

                foreach ($product->getLocationStocks() as $locationStock) {
                    $locationData[] = [
                        'product_id'        => $product->getId(),
                        'location_id'       => $locationStock->getLocationId()->getValue(),
                        'stock_quantity'    => $locationStock->getStockQuantity()->getValue(),
                        'open_box_quantity' => $locationStock->getOpenBoxQuantity()->getValue(),
                        'damaged_quantity'  => $locationStock->getDamagedQuantity()->getValue(),
                        'updated_at'        => $now,
                    ];
                }

                foreach ($product->getPendingTransactions() as $transaction) {
                    $transactionData[] = [
                        'tenant_id'       => $this->tenantId,
                        'product_id'      => $transaction->getProductId(),
                        'type'            => $transaction->getType()->getValue(),
                        'quantity_change' => $transaction->getQuantityChange(),
                        'condition'       => $transaction->getCondition()->getValue(),
                        'created_at'      => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
                        'reference_id'    => $transaction->getReference(),
                    ];
                }
            }

            if (!empty($productData)) {
                ProductModel::upsert(
                    $productData,
                    ['id'],
                    ['tenant_id', 'sku', 'name', 'department', 'reorder_threshold', 'updated_at']
                );
            }

            if (!empty($locationData)) {
                // SQLite in memory test does not have a composite primary key or unique constraint set
                // up for product_id + location_id, causing upsert to fail.
                // We'll fallback to updateOrCreate for locations, but wrapped in the transaction it's still fast.
                // Or try checking connection driver. For production postgres it works if unique constraint exists.
                // Let's use updateOrCreate for locationData to be safe since it's a pivot-like table
                foreach ($locationData as $loc) {
                    ProductLocationModel::updateOrCreate(
                        [
                            'product_id' => $loc['product_id'],
                            'location_id' => $loc['location_id'],
                        ],
                        [
                            'stock_quantity'     => $loc['stock_quantity'],
                            'open_box_quantity'  => $loc['open_box_quantity'],
                            'damaged_quantity'   => $loc['damaged_quantity'],
                            'updated_at'        => $loc['updated_at'],
                        ]
                    );
                }
            }

            if (!empty($transactionData)) {
                InventoryTransactionModel::insert($transactionData);
            }

            foreach ($products as $product) {
                $product->clearPendingTransactions();
            }
        });
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
