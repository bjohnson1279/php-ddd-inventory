<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Procurement\Events;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Procurement\Events\ReorderPointReachedEvent;
use DateTimeImmutable;

class ReorderPointReachedEventTest extends TestCase
{
    public function testCanCreateEvent()
    {
        $sku = 'TEST-SKU-123';
        $locationId = 'LOC-001';
        $currentQuantity = 10;
        $reorderPoint = 20;
        $reorderQuantity = 50;
        $occurredOn = new DateTimeImmutable();

        $event = new ReorderPointReachedEvent(
            $sku,
            $locationId,
            $currentQuantity,
            $reorderPoint,
            $reorderQuantity,
            $occurredOn
        );

        $this->assertEquals($sku, $event->sku);
        $this->assertEquals($locationId, $event->locationId);
        $this->assertEquals($currentQuantity, $event->currentQuantity);
        $this->assertEquals($reorderPoint, $event->reorderPoint);
        $this->assertEquals($reorderQuantity, $event->reorderQuantity);
    }

    public function testOccurredOnReturnsDateTime()
    {
        $occurredOn = new DateTimeImmutable();

        $event = new ReorderPointReachedEvent(
            'TEST-SKU-123',
            'LOC-001',
            10,
            20,
            50,
            $occurredOn
        );

        $this->assertSame($occurredOn, $event->occurredOn());
    }
}
