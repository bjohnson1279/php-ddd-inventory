<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\ReceiveInTransit;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ReceiveInTransitTest extends TestCase
{
    public function testExecuteThrowsExceptionWhenProductNotFound()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $eventsStub = $this->createStub(EventDispatcherInterface::class);

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) {
                return $sku->getValue() === 'UNKNOWN-SKU';
            }))
            ->willReturn(null);

        $useCase = new ReceiveInTransit($repositoryMock, $eventsStub);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product not found with SKU: UNKNOWN-SKU");

        $useCase->execute(new SKU('UNKNOWN-SKU'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteReceivesInTransitStockAndSavesProduct()
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

        $product->createInTransitAt(new LocationId('LOC-STOREFRONT'), new Quantity(5));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                // Initial stock is 10. Added 5 to in-transit, which shouldn't change stock quantity immediately for some methods but available might vary.
                // Upon receiving in transit, stock quantity goes from 10 to 15.
                // We should test that total stock quantity matches.
                return $p->getTotalStockQuantity()->getValue() === 15;
            }));

        $eventsMock->expects($this->atLeastOnce())
            ->method('dispatch');

        $useCase = new ReceiveInTransit($repositoryMock, $eventsMock);
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionWhenInsufficientInTransitStock()
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

        // Intentionally create only 2 in transit
        $product->createInTransitAt(new LocationId('LOC-STOREFRONT'), new Quantity(2));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->willReturn($product);

        $repositoryMock->expects($this->never())
            ->method('save');

        $eventsMock->expects($this->never())
            ->method('dispatch');

        $useCase = new ReceiveInTransit($repositoryMock, $eventsMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot receive in transit of 5 because only 2 is in transit.");

        // Attempt to receive 5, which is more than the 2 in transit
        $useCase->execute(new SKU('TSHIRT-L-RED'), new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }
}
