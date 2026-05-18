<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface;
use InventoryApp\Domain\Catalog\Entities\Product as CatalogProduct;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use Illuminate\Database\Capsule\Manager as Capsule;

class EloquentCatalogProductRepository implements CatalogProductRepositoryInterface
{
    public function findById(string $id): ?CatalogProduct
    {
        $row = Capsule::connection()->table('catalog_products')->where('id', $id)->first();
        if (!$row) return null;

        return new CatalogProduct($row->id, $row->name, $row->description, new Department($row->department));
    }

    public function save(CatalogProduct $product): void
    {
        Capsule::connection()->table('catalog_products')->updateOrInsert(
            ['id' => $product->getId()],
            [
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'department' => $product->getDepartment()->getValue(),
                'created_at' => date('c')
            ]
        );
    }

    public function delete(CatalogProduct $product): void
    {
        Capsule::connection()->table('catalog_products')->where('id', $product->getId())->delete();
    }
}
