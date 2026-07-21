<?php

namespace Tests\Unit\Application\Inventory\Listeners;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\Listeners\CreateInventoryItemOnVariantAdded;
use InventoryApp\Application\Inventory\UseCases\RegisterProduct;
use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;

class CreateInventoryItemOnVariantAddedTest extends TestCase
{
    public function testHandleCallsRegisterProduct(): void
    {
        $useCase = $this->createMock(RegisterProduct::class);
        $listener = new CreateInventoryItemOnVariantAdded($useCase);

        $event = new VariantAddedToCatalog(
            'p-1',
            'Test Product',
            new Department('ELECTRONICS'),
            new SKU('SKU-123')
        );

        $useCase->expects($this->once())
            ->method('execute')
            ->with(
                $this->isType('string'),
                'SKU-123',
                'Test Product (SKU-123)',
                'ELECTRONICS',
                'LOC-STOREFRONT',
                0
            );

        $listener->handle($event);
    }
}
