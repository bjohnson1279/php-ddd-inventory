<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;

class GetStockLevelTest extends TestCase
{
    public function testExecuteReturnsTotalStockQuantity()
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

        $useCase = new GetStockLevel($repositoryMock);
        $quantity = $useCase->execute('TSHIRT-L-RED');

        $this->assertEquals(10, $quantity);
    }
}
