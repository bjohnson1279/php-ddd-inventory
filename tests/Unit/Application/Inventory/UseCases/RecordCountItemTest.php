<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\StartInventoryCount;
use InventoryApp\Application\Inventory\UseCases\RecordCountItem;
use InventoryApp\Application\Inventory\UseCases\CompleteInventoryCount;
use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\InventoryCount;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\CountStatus;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class RecordCountItemTest extends TestCase
{
    private $countRepo;

    protected function setUp(): void
    {
        $this->countRepo = $this->createMock(InventoryCountRepositoryInterface::class);
    }

    public function testRecordCountItemUpdatesAggregate(): void
    {
        $count = InventoryCount::start('c-1');
        $this->countRepo->method('findById')->willReturn($count);
        $this->countRepo->expects($this->once())->method('save')
            ->with($this->callback(fn(InventoryCount $c) => count($c->getItems()) === 1));

        (new RecordCountItem($this->countRepo))->execute('c-1', 'SKU-1', 'LOC-A', 10);
    }

    public function testRecordCountItemThrowsWhenNotFound(): void
    {
        $this->countRepo->method('findById')->willReturn(null);
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/not found/');

        (new RecordCountItem($this->countRepo))->execute('ghost', 'SKU-1', 'LOC-A', 10);
    }

}
