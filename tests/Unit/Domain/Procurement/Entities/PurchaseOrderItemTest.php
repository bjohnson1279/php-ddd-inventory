<?php

namespace Tests\Unit\Domain\Procurement\Entities;

use InvalidArgumentException;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use PHPUnit\Framework\TestCase;

class PurchaseOrderItemTest extends TestCase
{
    public function testCanCreatePurchaseOrderItem(): void
    {
        $item = new PurchaseOrderItem('poi-1', 'variant-1', 10, 500);

        $this->assertEquals('poi-1', $item->id);
        $this->assertEquals('variant-1', $item->variantId);
        $this->assertEquals(10, $item->quantity);
        $this->assertEquals(500, $item->unitCostCents);
        $this->assertEquals(0, $item->getReceivedQuantity());
        $this->assertFalse($item->isFullyReceived());
    }

    public function testCannotCreateWithZeroQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Quantity must be greater than zero.");
        new PurchaseOrderItem('poi-1', 'variant-1', 0, 500);
    }

    public function testCannotCreateWithNegativeQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Quantity must be greater than zero.");
        new PurchaseOrderItem('poi-1', 'variant-1', -5, 500);
    }

    public function testCannotCreateWithNegativeUnitCost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unit cost cannot be negative.");
        new PurchaseOrderItem('poi-1', 'variant-1', 10, -500);
    }

    public function testCanReceiveValidAmount(): void
    {
        $item = new PurchaseOrderItem('poi-1', 'variant-1', 10, 500);
        $item->receive(5);
        $this->assertEquals(5, $item->getReceivedQuantity());
        $this->assertFalse($item->isFullyReceived());

        $item->receive(5);
        $this->assertEquals(10, $item->getReceivedQuantity());
        $this->assertTrue($item->isFullyReceived());
    }

    public function testCannotReceiveZeroAmount(): void
    {
        $item = new PurchaseOrderItem('poi-1', 'variant-1', 10, 500);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Receive amount must be greater than zero.");
        $item->receive(0);
    }

    public function testCannotReceiveNegativeAmount(): void
    {
        $item = new PurchaseOrderItem('poi-1', 'variant-1', 10, 500);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Receive amount must be greater than zero.");
        $item->receive(-5);
    }

    public function testCannotReceiveMoreThanOrderedQuantity(): void
    {
        $item = new PurchaseOrderItem('poi-1', 'variant-1', 10, 500);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot receive 11 items. Total received would exceed ordered quantity of 10.");
        $item->receive(11);
    }
}
