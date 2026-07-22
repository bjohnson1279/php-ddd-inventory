<?php

namespace Tests\Unit\Domain\Shipping\Events;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent;
use DateTimeImmutable;

class ShipmentStatusUpdatedEventTest extends TestCase
{
    public function test_it_creates_event_with_valid_properties()
    {
        $shipmentId = 'SHIP-123';
        $trackingNumber = 'TRACK-456';
        $status = 'SHIPPED';

        $event = new ShipmentStatusUpdatedEvent($shipmentId, $trackingNumber, $status);

        $this->assertEquals($shipmentId, $event->shipmentId);
        $this->assertEquals($trackingNumber, $event->trackingNumber);
        $this->assertEquals($status, $event->status);
    }

    public function test_it_sets_occurred_on_to_current_date_time()
    {
        $shipmentId = 'SHIP-123';
        $trackingNumber = 'TRACK-456';
        $status = 'SHIPPED';

        $before = new DateTimeImmutable();
        $event = new ShipmentStatusUpdatedEvent($shipmentId, $trackingNumber, $status);
        $after = new DateTimeImmutable();

        $this->assertInstanceOf(DateTimeImmutable::class, $event->occurredOn());

        // Assert the occurredOn time is between before and after object creations
        $this->assertGreaterThanOrEqual($before, $event->occurredOn());
        $this->assertLessThanOrEqual($after, $event->occurredOn());
    }
}
