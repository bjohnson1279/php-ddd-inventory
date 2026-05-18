<?php

namespace Tests\Unit\Domain\Inventory\Entities;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;

class ProductLowStockTest extends TestCase
{
    public function testProductIsLowStockWhenBelowThreshold(): void
    {
        $product = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(3),     // stock below threshold
            new Quantity(5)      // reorder threshold
        );

        $this->assertTrue($product->isLowStock());
    }

    public function testProductIsLowStockWhenAtThreshold(): void
    {
        $product = Product::create(
            'prod_124',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(5),     // stock equals threshold
            new Quantity(5)
        );

        $this->assertTrue($product->isLowStock());
    }

    public function testProductIsNotLowStockWhenAboveThreshold(): void
    {
        $product = Product::create(
            'prod_125',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(20),
            new Quantity(5)
        );

        $this->assertFalse($product->isLowStock());
    }

    public function testLowStockUsesTotalAcrossAllLocations(): void
    {
        // 6 units total across two locations, threshold is 5
        $product = Product::create(
            'prod_126',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(3),
            new Quantity(5)
        );

        $product->receiveStockAt(new LocationId('LOC-BACKROOM'), new Quantity(3));

        $this->assertFalse($product->isLowStock());
    }
}
