<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\CreateInTransit;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class CreateInTransitTest extends TestCase
{
    public function testExecuteCreatesInTransitAndSavesProduct()
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
                return $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getInTransitQuantity()->getValue() === 5;
            }));

        $useCase = new CreateInTransit($repositoryMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionWhenProductNotFound()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Product not found with SKU: TSHIRT-L-RED');

        $useCase = new CreateInTransit($repositoryMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }
}
