<?php

namespace InventoryApp\Application\Catalog\UseCases;

use InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface;
use InventoryApp\Domain\Catalog\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use Exception;

class CreateProductCatalog
{
    private CatalogProductRepositoryInterface $repository;

    public function __construct(CatalogProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $id, string $name, string $description, string $departmentValue): void
    {
        if ($this->repository->findById($id)) {
            throw new Exception("Catalog Product already exists with ID: " . $id);
        }

        $department = new Department($departmentValue);
        $product = new Product($id, $name, $description, $department);
        
        $this->repository->save($product);
    }
}
