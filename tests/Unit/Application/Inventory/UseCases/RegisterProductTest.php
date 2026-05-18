<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\RegisterProduct;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;

class RegisterProductTest extends TestCase
{
    public function testExecuteCreatesAndSavesNewProduct()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        
        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->willReturn(null);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getSku()->getValue() === 'TSHIRT-L-RED' && 
                       $p->getTotalStockQuantity()->getValue() === 10;
            }));

        $useCase = new RegisterProduct($repositoryMock);
        $useCase->execute('prod_123', 'TSHIRT-L-RED', 'Large Red T-Shirt', 'APPAREL', 'LOC-STOREFRONT', 10);
    }
}
