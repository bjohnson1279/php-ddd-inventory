<?php

namespace Tests\Unit\Domain\Inventory\Entities;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Entities\InventoryCount;
use InventoryApp\Domain\Inventory\ValueObjects\CountStatus;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Exception;

class InventoryCountTest extends TestCase
{
    public function testStartInitializesWithStartedStatus(): void
    {
        $count = InventoryCount::start('c-1');
        $this->assertEquals('c-1', $count->getId());
        $this->assertEquals(CountStatus::STARTED, $count->getStatus()->getValue());
        $this->assertEmpty($count->getItems());
    }

    public function testRecordCountAddsItems(): void
    {
        $count = InventoryCount::start('c-1');
        $count->recordCount(new SKU('SKU-1'), new Quantity(10));
        $count->recordCount(new SKU('SKU-2'), new Quantity(5));

        $items = $count->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals('SKU-1', $items[0]->getSku()->getValue());
        $this->assertEquals(10, $items[0]->getCountedQuantity()->getValue());
    }

    public function testRecordCountOverwritesExistingSku(): void
    {
        $count = InventoryCount::start('c-1');
        $count->recordCount(new SKU('SKU-1'), new Quantity(10));
        $count->recordCount(new SKU('SKU-1'), new Quantity(15));

        $items = $count->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals(15, $items[0]->getCountedQuantity()->getValue());
    }

    public function testCompleteTransitionsStatus(): void
    {
        $count = InventoryCount::start('c-1');
        $count->complete();
        $this->assertTrue($count->getStatus()->isCompleted());
    }

    public function testCannotRecordCountAfterCompletion(): void
    {
        $count = InventoryCount::start('c-1');
        $count->complete();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/already completed/');
        $count->recordCount(new SKU('SKU-1'), new Quantity(10));
    }

    public function testCannotCompleteTwice(): void
    {
        $count = InventoryCount::start('c-1');
        $count->complete();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/already completed/');
        $count->complete();
    }
}
