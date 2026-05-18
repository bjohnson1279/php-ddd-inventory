<?php

namespace Tests\Unit\Application\Catalog\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface;
use InventoryApp\Domain\Catalog\Entities\Product;
use Exception;

class CreateProductCatalogTest extends TestCase
{
    public function testExecuteCreatesAndSavesNewCatalogProduct(): void
    {
        $repositoryMock = $this->createMock(CatalogProductRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('findById')->willReturn(null);
        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return $p->getName() === 'Graphic Tee'
                    && $p->getDepartment()->getValue() === 'APPAREL';
            }));

        $useCase = new CreateProductCatalog($repositoryMock);
        $useCase->execute('prod_1', 'Graphic Tee', 'A cool shirt', 'APPAREL');
    }

    public function testExecuteThrowsWhenProductAlreadyExists(): void
    {
        $existing = new Product('prod_1', 'Graphic Tee', 'A cool shirt', new \InventoryApp\Domain\Inventory\ValueObjects\Department('APPAREL'));

        $repositoryMock = $this->createMock(CatalogProductRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('findById')->willReturn($existing);
        $repositoryMock->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/already exists/i');

        $useCase = new CreateProductCatalog($repositoryMock);
        $useCase->execute('prod_1', 'Graphic Tee', 'A cool shirt', 'APPAREL');
    }
}
