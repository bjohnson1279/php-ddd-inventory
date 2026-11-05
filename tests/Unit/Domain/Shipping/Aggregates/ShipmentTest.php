<?php

namespace Tests\Unit\Domain\Shipping\Aggregates;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use InventoryApp\Domain\Shared\ValueObjects\Money;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use PHPUnit\Framework\TestCase;

class ShipmentTest extends TestCase
{
    private function createShipment(
        Money|int $rate = 500,
        ShipmentStatus $status = ShipmentStatus::LabelGenerated
    ): Shipment {
        return new Shipment(
            id: 'SHIP-123',
            sku: 'SKU-TEST',
            quantity: 2,
            destinationAddress: '123 Test St, Test City, TS 12345',
            carrier: 'FedEx',
            trackingNumber: 'TRK-987654',
            labelUrl: 'https://shipping.example.com/labels/SHIP-123.pdf',
            shippingRateCents: $rate,
            status: $status,
            createdAt: new DateTimeImmutable('2025-01-01 10:00:00')
        );
    }

    public function testCanCreateShipmentWithIntegerRate(): void
    {
        $shipment = $this->createShipment(1500);

        $this->assertEquals('SHIP-123', $shipment->id);
        $this->assertEquals('SKU-TEST', $shipment->sku);
        $this->assertEquals(2, $shipment->quantity);
        $this->assertEquals('123 Test St, Test City, TS 12345', $shipment->destinationAddress);
        $this->assertEquals('FedEx', $shipment->carrier);
        $this->assertEquals('TRK-987654', $shipment->trackingNumber);
        $this->assertEquals('https://shipping.example.com/labels/SHIP-123.pdf', $shipment->labelUrl);
        $this->assertEquals(1500, $shipment->shippingRateCents);
        $this->assertEquals(1500, $shipment->getShippingRateCents());
        $this->assertInstanceOf(Money::class, $shipment->getShippingRate());
        $this->assertEquals(1500, $shipment->getShippingRate()->getAmountInCents());
        $this->assertEquals('USD', $shipment->getShippingRate()->getCurrency());
        $this->assertEquals(ShipmentStatus::LabelGenerated, $shipment->getStatus());
        $this->assertEquals('2025-01-01 10:00:00', $shipment->createdAt->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-01-01 10:00:00', $shipment->updatedAt->format('Y-m-d H:i:s'));
    }

    public function testCanCreateShipmentWithMoneyValueObject(): void
    {
        $money = Money::fromCents(2750, 'EUR');
        $shipment = $this->createShipment($money);

        $this->assertEquals(2750, $shipment->shippingRateCents);
        $this->assertEquals(2750, $shipment->getShippingRateCents());
        $this->assertTrue($shipment->getShippingRate()->equals($money));
        $this->assertEquals('EUR', $shipment->getShippingRate()->getCurrency());
    }

    public function testCannotCreateShipmentWithNegativeIntegerRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipping rate cannot be negative.');

        $this->createShipment(-100);
    }

    public function testCannotCreateShipmentWithNegativeMoneyRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipping rate cannot be negative.');

        $negativeMoney = Money::fromCents(-500, 'USD');
        $this->createShipment($negativeMoney);
    }

    public function testCanUpdateStatusThroughAllowedLifecycle(): void
    {
        $shipment = $this->createShipment(500, ShipmentStatus::LabelGenerated);
        $initialUpdatedAt = $shipment->updatedAt;

        $shipment->updateStatus(ShipmentStatus::InTransit);
        $this->assertEquals(ShipmentStatus::InTransit, $shipment->getStatus());

        $shipment->updateStatus(ShipmentStatus::Delivered);
        $this->assertEquals(ShipmentStatus::Delivered, $shipment->getStatus());
        $this->assertGreaterThanOrEqual($initialUpdatedAt, $shipment->updatedAt);
    }

    public function testCannotUpdateStatusFromDeliveredTerminalState(): void
    {
        $shipment = $this->createShipment(500, ShipmentStatus::Delivered);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot transition status from terminal state: delivered');

        $shipment->updateStatus(ShipmentStatus::InTransit);
    }

    public function testCannotUpdateStatusFromFailedTerminalState(): void
    {
        $shipment = $this->createShipment(500, ShipmentStatus::Failed);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot transition status from terminal state: failed');

        $shipment->updateStatus(ShipmentStatus::InTransit);
    }
}
