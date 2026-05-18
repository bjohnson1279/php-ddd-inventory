<?php

namespace Tests\Unit\Application\Catalog\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
use InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface;
use InventoryApp\Domain\Catalog\Entities\Product;
use InventoryApp\Domain\Shared\Events\EventDispatcher;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use Exception;

class AddVariantTest extends TestCase
{
    private function makeCatalogProduct(): Product
    {
        return new Product('p1', 'Graphic Tee', 'A cool shirt', new Department('APPAREL'));
    }

    public function testExecuteAddsVariantAndSaves(): void
    {
        $product = $this->makeCatalogProduct();

        $repositoryMock = $this->createMock(CatalogProductRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('findById')->willReturn($product);
        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $p) {
                return count($p->getVariants()) === 1
                    && $p->getVariants()[0]->getSku()->getValue() === 'TEE-L-RED';
            }));

        $dispatcher = new EventDispatcher();
        $useCase = new AddVariant($repositoryMock, $dispatcher);
        $useCase->execute('p1', 'v1', 'TEE-L-RED', ['size' => 'L', 'color' => 'Red'], 29.99);
    }

    public function testExecuteDispatchesVariantAddedToCatalogEvent(): void
    {
        $product = $this->makeCatalogProduct();

        $repositoryMock = $this->createMock(CatalogProductRepositoryInterface::class);
        $repositoryMock->method('findById')->willReturn($product);
        $repositoryMock->method('save');

        $dispatchedEvents = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->subscribe(
            \InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog::class,
            function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
            }
        );

        $useCase = new AddVariant($repositoryMock, $dispatcher);
        $useCase->execute('p1', 'v1', 'TEE-L-RED', ['size' => 'L'], 29.99);

        $this->assertCount(1, $dispatchedEvents);
        $this->assertEquals('TEE-L-RED', $dispatchedEvents[0]->getSku()->getValue());
    }

    public function testExecuteThrowsWhenProductNotFound(): void
    {
        $repositoryMock = $this->createMock(CatalogProductRepositoryInterface::class);
        $repositoryMock->expects($this->once())->method('findById')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $useCase = new AddVariant($repositoryMock, new EventDispatcher());
        $useCase->execute('bad-id', 'v1', 'TEE-L-RED', [], 29.99);
    }
}
