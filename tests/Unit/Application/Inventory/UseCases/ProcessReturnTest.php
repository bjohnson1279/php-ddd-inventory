<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\ProcessReturn;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;

class ProcessReturnTest extends TestCase
{
    public function testExecuteProcessesReturnAndSavesProduct()
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
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getOpenBoxQuantity()->getValue() === 1;
            }));

        $useCase = new ProcessReturn($repositoryMock);
        $useCase->execute('TSHIRT-L-RED', 'LOC-STOREFRONT', 1, 'OPEN_BOX');
    }
}
