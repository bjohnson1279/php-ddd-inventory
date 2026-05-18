<?php

namespace Tests\Unit\Domain\Inventory\Entities;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;

class ProductTest extends TestCase
{
    private function createProduct()
    {
        return Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10),
            new Quantity(5)
        );
    }

    public function testCanCreateProductWithInitialStock()
    {
        $product = $this->createProduct();

        $this->assertEquals(10, $product->getTotalStockQuantity()->getValue());
        $this->assertEquals(10, $product->getStockAt(new LocationId('LOC-STOREFRONT'))->getStockQuantity()->getValue());
    }

    public function testReceiveStockIncreasesQuantity()
    {
        $product = $this->createProduct();
        $product->receiveStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(5));

        $this->assertEquals(15, $product->getTotalStockQuantity()->getValue());
    }

    public function testDispatchStockDecreasesQuantity()
    {
        $product = $this->createProduct();
        $product->dispatchStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(4));

        $this->assertEquals(6, $product->getTotalStockQuantity()->getValue());
    }

    public function testDispatchStockThrowsExceptionWhenInsufficient()
    {
        $product = $this->createProduct();

        $this->expectException(InsufficientStockException::class);
        $product->dispatchStockAt(new LocationId('LOC-STOREFRONT'), new Quantity(15));
    }

    public function testProcessSaleDecreasesQuantity()
    {
        $product = $this->createProduct();
        $product->processSaleAt(new LocationId('LOC-STOREFRONT'), new Quantity(2));

        $this->assertEquals(8, $product->getTotalStockQuantity()->getValue());
    }

    public function testProcessReturnUpdatesCorrectConditionQuantity()
    {
        $product = $this->createProduct();
        $product->processReturnAt(new LocationId('LOC-STOREFRONT'), new Quantity(1), new Condition(Condition::OPEN_BOX));

        $this->assertEquals(10, $product->getTotalStockQuantity()->getValue());
        $this->assertEquals(1, $product->getStockAt(new LocationId('LOC-STOREFRONT'))->getOpenBoxQuantity()->getValue());
    }
    
    public function testTransferStockMovesQuantityBetweenLocations()
    {
        $product = $this->createProduct();
        $product->transferStock(new LocationId('LOC-STOREFRONT'), new LocationId('LOC-BACKROOM'), new Quantity(3));
        
        $this->assertEquals(7, $product->getStockAt(new LocationId('LOC-STOREFRONT'))->getStockQuantity()->getValue());
        $this->assertEquals(3, $product->getStockAt(new LocationId('LOC-BACKROOM'))->getStockQuantity()->getValue());
        $this->assertEquals(10, $product->getTotalStockQuantity()->getValue());
    }
}
