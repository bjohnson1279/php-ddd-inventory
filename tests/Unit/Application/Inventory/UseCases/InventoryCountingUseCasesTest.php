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

class InventoryCountingUseCasesTest extends TestCase
{
    private $countRepo;
    private $productRepo;

    protected function setUp(): void
    {
        $this->countRepo = $this->createMock(InventoryCountRepositoryInterface::class);
        $this->productRepo = $this->createMock(ProductRepositoryInterface::class);
    }

    public function testStartInventoryCountSavesNewAggregate(): void
    {
        $this->countRepo->expects($this->once())->method('save')
            ->with($this->callback(fn(InventoryCount $c) => $c->getId() === 'c-1'));

        (new StartInventoryCount($this->countRepo))->execute('c-1');
    }

    public function testRecordCountItemUpdatesAggregate(): void
    {
        $count = InventoryCount::start('c-1');
        $this->countRepo->method('findById')->willReturn($count);
        $this->countRepo->expects($this->once())->method('save')
            ->with($this->callback(fn(InventoryCount $c) => count($c->getItems()) === 1));

        (new RecordCountItem($this->countRepo))->execute('c-1', 'SKU-1', 10);
    }

    public function testRecordCountItemThrowsWhenNotFound(): void
    {
        $this->countRepo->method('findById')->willReturn(null);
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/not found/');

        (new RecordCountItem($this->countRepo))->execute('ghost', 'SKU-1', 10);
    }

    public function testCompleteInventoryCountReconcilesProducts(): void
    {
        $count = InventoryCount::start('c-1');
        $count->recordCount(new SKU('SKU-1'), new Quantity(15));
        
        $product = Product::create(
            'p-1', new SKU('SKU-1'), 'Test', new Department('D1'), new LocationId('LOC-STOREFRONT'), new Quantity(10)
        );
        $product->clearPendingTransactions();

        $this->countRepo->method('findById')->willReturn($count);
        $this->productRepo->method('findBySku')->willReturn($product);
        
        $this->countRepo->expects($this->once())->method('save')
            ->with($this->callback(fn(InventoryCount $c) => $c->getStatus()->isCompleted()));
        
        $this->productRepo->expects($this->once())->method('save')
            ->with($this->callback(fn(Product $p) => count($p->getPendingTransactions()) === 1));

        (new CompleteInventoryCount($this->countRepo, $this->productRepo, $this->createStub(EventDispatcherInterface::class)))->execute('c-1');
        
        $txns = $product->getPendingTransactions();
        $this->assertEquals(5, $txns[0]->getQuantityChange()); // 15 counted - 10 current = +5 adjustment
    }
}
