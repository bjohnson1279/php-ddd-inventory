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
        $skuObj = new SKU('TSHIRT-L-RED');

        $product = Product::create(
            'prod_123',
            $skuObj,
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($skuObj) {
                return $sku->getValue() === $skuObj->getValue();
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getInTransitQuantity()->getValue() === 5;
            }));

        $useCase = new CreateInTransit($repositoryMock);
        $useCase->execute($skuObj, new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteThrowsExceptionWhenProductNotFound()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $skuObj = new SKU('TSHIRT-L-RED');

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($skuObj) {
                return $sku->getValue() === $skuObj->getValue();
            }))
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Product not found with SKU: ' . $skuObj->getValue());

        $useCase = new CreateInTransit($repositoryMock);
        $useCase->execute($skuObj, new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }

    public function testExecuteAddsToExistingInTransitStock()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $skuObj = new SKU('TSHIRT-L-RED');

        $product = Product::create(
            'prod_123',
            $skuObj,
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );
        // Add existing in transit stock
        $product->createInTransitAt(new LocationId('LOC-STOREFRONT'), new Quantity(5));

        $repositoryMock->expects($this->once())
            ->method('findBySku')
            ->with($this->callback(function (SKU $sku) use ($skuObj) {
                return $sku->getValue() === $skuObj->getValue();
            }))
            ->willReturn($product);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getInTransitQuantity()->getValue() === 10;
            }));

        $useCase = new CreateInTransit($repositoryMock);
        $useCase->execute($skuObj, new Quantity(5), new LocationId('LOC-STOREFRONT'));
    }
}