<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\TransferStock;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class TransferStockTest extends TestCase
{
    public function testExecuteTransfersStockBetweenLocations(): void
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
                $storefront = $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getStockQuantity()->getValue();
                $backroom   = $p->getStockAt(new LocationId('LOC-BACKROOM'))->getStockQuantity()->getValue();
                return $storefront === 6 && $backroom === 4;
            }));

        $useCase = new TransferStock($repositoryMock, $this->createStub(EventDispatcherInterface::class));
        $useCase->execute(new SKU('TSHIRT-L-RED'), new LocationId('LOC-STOREFRONT'), new LocationId('LOC-BACKROOM'), new Quantity(4));
    }

    public function testExecuteThrowsWhenProductNotFound(): void
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $repositoryMock->method('findBySku')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $useCase = new TransferStock($repositoryMock, $this->createStub(EventDispatcherInterface::class));
        $useCase->execute(new SKU('GHOST-SKU'), new LocationId('LOC-A'), new LocationId('LOC-B'), new Quantity(1));
    }
}
