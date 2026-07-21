<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\FulfillAllocation;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Events\StockDispatched;
use InventoryApp\Domain\Inventory\Events\LowStockDetected;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class FulfillAllocationTest extends TestCase
{
    public function testExecuteFulfillsAllocationAndSavesProduct()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $eventsMock = $this->createMock(EventDispatcherInterface::class);

        $product = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );
        // Clear events generated during creation (e.g. StockReceived)
        $product->releaseEvents();

        $product->allocateStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(5));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $s) {
                return $s->getValue() === 'TSHIRT-L-RED';
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                // Stock should be reduced by fulfilled allocation
                // Allocated stock decreases
                // Total stock decreases
                $stock = $p->getStockAt(new LocationId('LOC-STOREFRONT'));
                return $stock->getAllocatedQuantity()->getValue() === 3
                    && $stock->getStockQuantity()->getValue() === 8;
            }));

        $eventsMock->expects($this->atLeastOnce())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof StockDispatched || $event instanceof LowStockDetected;
            }));

        $useCase = new FulfillAllocation($repositoryMock, $eventsMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(2), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionIfProductNotFound()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $eventsMock = $this->createMock(EventDispatcherInterface::class);

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $s) {
                return $s->getValue() === 'INVALID-SKU';
            }))
            ->willReturn(null);

        $repositoryMock->expects($this->never())
            ->method('save');

        $eventsMock->expects($this->never())
            ->method('dispatch');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product not found with SKU: INVALID-SKU");

        $useCase = new FulfillAllocation($repositoryMock, $eventsMock);
        $useCase->execute(new SKU('INVALID-SKU'), new Quantity(2), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionIfFulfillAllocationFails()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $eventsMock = $this->createMock(EventDispatcherInterface::class);

        $product = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );
        $product->releaseEvents();

        // Allocate only 1
        $product->allocateStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(1));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $s) {
                return $s->getValue() === 'TSHIRT-L-RED';
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->never())
            ->method('save');

        $eventsMock->expects($this->never())
            ->method('dispatch');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot fulfill allocation of 2 because only 1 is allocated.");

        $useCase = new FulfillAllocation($repositoryMock, $eventsMock);
        // Try to fulfill 2, but only 1 is allocated
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(2), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionIfInsufficientStockForFulfillment()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $eventsMock = $this->createMock(EventDispatcherInterface::class);

        $product = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );
        $product->releaseEvents();

        // Allocate 5
        $product->allocateStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(5));

        // Dispatch 8, leaving only 2 physical stock
        $product->dispatchStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(8));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $s) {
                return $s->getValue() === 'TSHIRT-L-RED';
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->never())
            ->method('save');

        $eventsMock->expects($this->never())
            ->method('dispatch');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot fulfill allocation of 5 because only 2 is in stock.");

        $useCase = new FulfillAllocation($repositoryMock, $eventsMock);
        // Try to fulfill 5, which is allocated, but stock is only 2
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }
}
