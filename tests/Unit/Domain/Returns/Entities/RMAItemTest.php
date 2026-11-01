<?php

namespace Tests\Unit\Domain\Returns\Entities;

use InvalidArgumentException;
use InventoryApp\Domain\Returns\Entities\RMAItem;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InventoryApp\Domain\Returns\Enums\RMAItemStatus;
use InventoryApp\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class RMAItemTest extends TestCase
{
    public function testCanCreateRMAItemWithIntegerCents(): void
    {
        $item = new RMAItem('item-1', 'variant-10', 5, 1200);

        $this->assertEquals('item-1', $item->getId());
        $this->assertEquals('variant-10', $item->getVariantId());
        $this->assertEquals(5, $item->getQuantity());
        $this->assertEquals(1200, $item->getUnitCostCents());
        $this->assertEquals(1200, $item->getUnitCost()->getAmountInCents());
        $this->assertEquals(6000, $item->getTotalCost()->getAmountInCents());
        $this->assertEquals(0, $item->getReceivedQuantity());
        $this->assertEquals(RMAItemStatus::Pending, $item->getStatus());
        $this->assertNull($item->getDisposition());
        $this->assertFalse($item->isFullyProcessed());
    }

    public function testCanCreateRMAItemWithMoneyValueObject(): void
    {
        $money = Money::fromCents(2500, 'USD');
        $item = new RMAItem('item-2', 'variant-20', 3, $money);

        $this->assertEquals(2500, $item->getUnitCostCents());
        $this->assertTrue($item->getUnitCost()->equals($money));
        $this->assertEquals(7500, $item->getTotalCost()->getAmountInCents());
        $this->assertEquals('USD', $item->getTotalCost()->getCurrency());
    }

    public function testCannotCreateWithZeroQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero.');
        new RMAItem('item-1', 'variant-1', 0, 1000);
    }

    public function testCannotCreateWithNegativeQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero.');
        new RMAItem('item-1', 'variant-1', -1, 1000);
    }

    public function testCannotCreateWithNegativeUnitCost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit cost cannot be negative.');
        new RMAItem('item-1', 'variant-1', 5, -100);
    }

    public function testCannotCreateWithNegativeReceivedQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Received quantity cannot be negative.');
        new RMAItem('item-1', 'variant-1', 5, 100, -1);
    }

    public function testCanReceivePartialAmount(): void
    {
        $item = new RMAItem('item-1', 'variant-1', 5, 1000);
        $item->receive(2, RMADisposition::Restock);

        $this->assertEquals(2, $item->getReceivedQuantity());
        $this->assertEquals(RMADisposition::Restock, $item->getDisposition());
        $this->assertEquals(RMAItemStatus::Pending, $item->getStatus());
        $this->assertFalse($item->isFullyProcessed());
    }

    public function testCanReceiveFullAmount(): void
    {
        $item = new RMAItem('item-1', 'variant-1', 5, 1000);
        $item->receive(5, RMADisposition::Quarantine);

        $this->assertEquals(5, $item->getReceivedQuantity());
        $this->assertEquals(RMADisposition::Quarantine, $item->getDisposition());
        $this->assertEquals(RMAItemStatus::Received, $item->getStatus());
        $this->assertTrue($item->isFullyProcessed());
    }

    public function testCannotReceiveZeroAmount(): void
    {
        $item = new RMAItem('item-1', 'variant-1', 5, 1000);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Receive quantity must be greater than zero.');
        $item->receive(0, RMADisposition::Restock);
    }

    public function testCannotReceiveExceedingExpectedQuantity(): void
    {
        $item = new RMAItem('item-1', 'variant-1', 5, 1000);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot receive 6 units. Total received would exceed expected quantity of 5.');
        $item->receive(6, RMADisposition::Restock);
    }

    public function testCanRejectItem(): void
    {
        $item = new RMAItem('item-1', 'variant-1', 5, 1000);
        $item->reject();

        $this->assertEquals(RMAItemStatus::Rejected, $item->getStatus());
        $this->assertTrue($item->isFullyProcessed());
    }
}
