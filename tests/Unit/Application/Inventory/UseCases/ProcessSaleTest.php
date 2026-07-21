<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\ProcessSale;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;

class ProcessSaleTest extends TestCase
{
    public function testExecuteProcessesSaleAndSavesProduct()
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
                return $p->getTotalStockQuantity()->getValue() === 8;
            }));

        $useCase = new ProcessSale($repositoryMock, $this->createStub(EventDispatcherInterface::class));
        $useCase->execute('TSHIRT-L-RED', 'LOC-STOREFRONT', 2);
    }

    public function testExecuteBulkProcessesSalesAndSavesProducts()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);

        $product1 = Product::create(
            'prod_123',
            new SKU('TSHIRT-L-RED'),
            'Large Red T-Shirt',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(10)
        );

        $product2 = Product::create(
            'prod_456',
            new SKU('PANTS-M-BLK'),
            'Medium Black Pants',
            new Department('APPAREL'),
            new LocationId('LOC-STOREFRONT'),
            new Quantity(5)
        );

        $repositoryMock->expects($this->once())
            ->method('findBySkus')
            ->willReturn([
                'TSHIRT-L-RED' => $product1,
                'PANTS-M-BLK'  => $product2
            ]);

        $repositoryMock->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function (array $products) {
                return count($products) === 2 &&
                       $products[0]->getTotalStockQuantity()->getValue() === 8 &&
                       $products[1]->getTotalStockQuantity()->getValue() === 4;
            }));

        $useCase = new ProcessSale($repositoryMock, $this->createStub(EventDispatcherInterface::class));
        $useCase->executeBulk([
            ['sku' => 'TSHIRT-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 2],
            ['sku' => 'PANTS-M-BLK',  'location' => 'LOC-STOREFRONT', 'quantity' => 1]
        ]);
    }
}
