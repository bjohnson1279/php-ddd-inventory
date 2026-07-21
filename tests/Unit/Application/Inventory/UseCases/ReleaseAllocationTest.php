<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\ReleaseAllocation;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class ReleaseAllocationTest extends TestCase
{
    public function testExecuteReleasesAllocationAndSavesProduct(): void
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

        // Pre-allocate some stock to release later
        $product->allocateStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(4));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $s) {
                return $s->getValue() === 'TSHIRT-L-RED';
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                $allocated = $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getAllocatedQuantity()->getValue();
                // 4 initially allocated, 2 released, so 2 should remain
                return $allocated === 2;
            }));

        $useCase = new ReleaseAllocation($repositoryMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(2), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsWhenProductNotFound(): void
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $repositoryMock->method('findBySku')->willReturn(null);

        $repositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Product not found with SKU: GHOST-SKU');

        $useCase = new ReleaseAllocation($repositoryMock);
        $useCase->execute(new SKU('GHOST-SKU'), new Quantity(1), new LocationId('LOC-A'));
    }

    public function testExecuteThrowsWhenReleasingMoreThanAllocated(): void
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

        $product->allocateStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(2));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $repositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot release allocation of 5 because only 2 is allocated.');

        $useCase = new ReleaseAllocation($repositoryMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteReleasesAllocationFromCorrectLocation(): void
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

        $product->receiveStockAt(new LocationId('LOC-BACKROOM'), new Quantity(15), 'INITIAL_STOCK');

        // Pre-allocate stock at both locations
        $product->allocateStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(4));
        $product->allocateStockAt(new LocationId('LOC-BACKROOM'), new Quantity(8));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                $storefrontAllocated = $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getAllocatedQuantity()->getValue();
                $backroomAllocated = $p->getStockAt(new LocationId('LOC-BACKROOM'))->getAllocatedQuantity()->getValue();

                // Storefront releases 3 (4 - 3 = 1)
                // Backroom remains unchanged (8)
                return $storefrontAllocated === 1 && $backroomAllocated === 8;
            }));

        $useCase = new ReleaseAllocation($repositoryMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(3), new LocationId('LOC-STOREFRONT'));
    }
}
