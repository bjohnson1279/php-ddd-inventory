<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\ProcessSaleBatch;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ProcessSaleBatchTest extends TestCase
{
    public function testExecuteProcessesSaleBatchAndSavesProducts()
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
                'PANTS-M-BLK'  => $product2,
            ]);

        $repositoryMock->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(function (array $products) {
                if (count($products) !== 2) return false;

                $p1Match = $products[0]->getSku()->getValue() === 'TSHIRT-L-RED' && $products[0]->getTotalStockQuantity()->getValue() === 8;
                $p2Match = $products[1]->getSku()->getValue() === 'PANTS-M-BLK' && $products[1]->getTotalStockQuantity()->getValue() === 4;

                return $p1Match && $p2Match;
            }));

        $useCase = new ProcessSaleBatch($repositoryMock, $this->createStub(EventDispatcherInterface::class));
        $useCase->execute([
            ['sku' => 'TSHIRT-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 2],
            ['sku' => 'PANTS-M-BLK', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
        ], 'ORDER-123');
    }

    public function testExecuteThrowsExceptionIfSkuNotFound()
    {
        $repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $repositoryMock->method('findBySkus')->willReturn([]);

        $useCase = new ProcessSaleBatch($repositoryMock, $this->createStub(EventDispatcherInterface::class));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Product not found with SKU: INVALID-SKU');

        $useCase->execute([
            ['sku' => 'INVALID-SKU', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
        ]);
    }
}
