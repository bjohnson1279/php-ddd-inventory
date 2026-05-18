<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;

class DispatchStockTest extends TestCase
{
    public function testExecuteDispatchesStockAndSavesProduct()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        
        $product = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->equalTo(new SKU('TSHIRT-L-RED')))
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getTotalStockQuantity()->getValue() === 5;
            }));

        $useCase = new DispatchStock($repositoryMock);
        $useCase->execute('TSHIRT-L-RED', 'LOC-STOREFRONT', 5);
    }
}
