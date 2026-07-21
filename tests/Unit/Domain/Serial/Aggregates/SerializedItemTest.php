<?php

namespace Tests\Unit\Domain\Serial\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use InventoryApp\Domain\Serial\Events\SerialStatusChanged;

class SerializedItemTest extends TestCase
{
    private function createItem(SerializedItemStatus $initialStatus = SerializedItemStatus::Pending): SerializedItem
    {
        return new SerializedItem(
            'item-1',
            'variant-1',
            new SerialNumber('SN-123'),
            'tenant-1',
            'loc-1',
            $initialStatus
        );
    }

    public function testInitialStatusIsPending()
    {
        $item = $this->createItem();
        $this->assertEquals(SerializedItemStatus::Pending, $item->status());
        $this->assertFalse($item->isAvailable());
    }

    public function testReceiveChangesStatusToInStock()
    {
        $item = $this->createItem();
        $item->receive('loc-2', 'actor-1', 'PO-123');

        $this->assertEquals(SerializedItemStatus::InStock, $item->status());
        $this->assertTrue($item->isAvailable());
        $this->assertEquals('loc-2', $item->locationId());

        $events = $item->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SerialStatusChanged::class, $events[0]);
    }

    public function testSellChangesStatusToSold()
    {
        $item = $this->createItem(SerializedItemStatus::InStock);
        $item->sell('SALE-123', 'actor-1');

        $this->assertEquals(SerializedItemStatus::Sold, $item->status());
        $this->assertFalse($item->isAvailable());
    }

    public function testWriteOffSucceedsWhenInStock()
    {
        $item = $this->createItem(SerializedItemStatus::InStock);
        $item->writeOff('Damaged in warehouse', 'actor-1', 'REF-123');

        $this->assertEquals(SerializedItemStatus::WrittenOff, $item->status());
        $this->assertFalse($item->isAvailable());

        $events = $item->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SerialStatusChanged::class, $events[0]);
        $this->assertEquals('Damaged in warehouse', $events[0]->reason);
        $this->assertEquals('actor-1', $events[0]->actorId);
        $this->assertEquals('REF-123', $events[0]->referenceId);
    }

    public function testWriteOffThrowsWhenNotAvailable()
    {
        $this->expectException(\DomainException::class);

        $item = $this->createItem(SerializedItemStatus::Pending);
        $item->writeOff('Damaged', 'actor-1');
    }

    public function testInvalidTransitionThrows()
    {
        $this->expectException(\DomainException::class);

        $item = $this->createItem(SerializedItemStatus::Sold);
        // A sold item cannot be directly written off according to SerializedItemStatus allowed transitions
        $item->writeOff('Lost', 'actor-1');
    }
}
