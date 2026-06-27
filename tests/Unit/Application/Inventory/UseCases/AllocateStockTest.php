<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\AllocateStock;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientAvailableStockException;
use Exception;

class AllocateStockTest extends TestCase
{
    public function testExecuteAllocatesStockAndSavesProduct()
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
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'TSHIRT-L-RED';
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                $locationStock = $p->getStockAt(new LocationId('LOC-STOREFRONT'));
                return $locationStock->getAllocatedQuantity()->getValue() === 5;
            }));

        $useCase = new AllocateStock($repositoryMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionWhenProductNotFound()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->willReturn(null);

        $repositoryMock->expects($this->never())
            ->method('save');

        $useCase = new AllocateStock($repositoryMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product not found with SKU: INVALID-SKU");

        $useCase->execute(new SKU('INVALID-SKU'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionWhenInsufficientAvailableStock()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);

        $product = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10) // 10 in stock
        );

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'TSHIRT-L-RED';
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->never())
            ->method('save');

        $useCase = new AllocateStock($repositoryMock);

        $this->expectException(InsufficientAvailableStockException::class);

        // Attempting to allocate 15, which is more than the 10 available
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(15), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteAccumulatesAllocatedStock()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);

        $product = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(20)
        );

        // Setup initial allocation
        $product->allocateStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(5));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'TSHIRT-L-RED';
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                $locationStock = $p->getStockAt(new LocationId('LOC-STOREFRONT'));
                // 5 initial + 7 new = 12 total allocated
                return $locationStock->getAllocatedQuantity()->getValue() === 12;
            }));

        $useCase = new AllocateStock($repositoryMock);

        // Allocate 7 more
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(7), new LocationId('LOC-STOREFRONT'));
    }
}
