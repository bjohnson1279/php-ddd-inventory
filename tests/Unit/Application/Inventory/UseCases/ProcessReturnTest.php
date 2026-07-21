<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\ProcessReturn;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;

class ProcessReturnTest extends TestCase
{
    public function testExecuteProcessesReturnAndSavesProduct()
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
                return $p->getStockAt(new LocationId('LOC-STOREFRONT'))->getOpenBoxQuantity()->getValue() === 1;
            }));

        $useCase = new ProcessReturn($repositoryMock, $this->createStub(EventDispatcherInterface::class));
        $useCase->execute('TSHIRT-L-RED', 'LOC-STOREFRONT', 1, 'OPEN_BOX');
    }

    public function testExecuteBulkProcessesReturnsAndSavesProducts()
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
                       $products[0]->getStockAt(new LocationId('LOC-STOREFRONT'))->getOpenBoxQuantity()->getValue() === 1 &&
                       $products[1]->getStockAt(new LocationId('LOC-STOREFRONT'))->getDamagedQuantity()->getValue() === 2;
            }));

        $useCase = new ProcessReturn($repositoryMock, $this->createStub(EventDispatcherInterface::class));
        $useCase->executeBulk([
            ['sku' => 'TSHIRT-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1, 'condition' => 'OPEN_BOX'],
            ['sku' => 'PANTS-M-BLK',  'location' => 'LOC-STOREFRONT', 'quantity' => 2, 'condition' => 'DAMAGED']
        ]);
    }
}
