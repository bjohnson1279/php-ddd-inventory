<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\UpdateShipmentStatus;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent;
use DateTimeImmutable;
use Exception;
use DomainException;

class UpdateShipmentStatusTest extends TestCase
{
    public function testExecuteSuccessfullyUpdatesStatus(): void
    {
        $shipmentRepositoryMock = $this->createMock(ShipmentRepositoryInterface::class);
        $outboxRepositoryMock = $this->createMock(OutboxRepositoryInterface::class);

        $shipment = new Shipment(
            id: 'ship_123',
            sku: 'SKU-001',
            quantity: 1,
            destinationAddress: '123 Main St',
            carrier: 'UPS',
            trackingNumber: '1Z9999999999999999',
            labelUrl: 'http://example.com/label.pdf',
            shippingRateCents: 1000,
            status: ShipmentStatus::LabelGenerated,
            createdAt: new DateTimeImmutable()
        );

        $shipmentRepositoryMock->expects($this->once())
            ->method('findById')
            ->with('ship_123')
            ->willReturn($shipment);

        $shipmentRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Shipment $s) {
                return $s->id === 'ship_123' && $s->getStatus() === ShipmentStatus::InTransit;
            }));

        $outboxRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ShipmentStatusUpdatedEvent $event) {
                return $event->shipmentId === 'ship_123'
                    && $event->trackingNumber === '1Z9999999999999999'
                    && $event->status === ShipmentStatus::InTransit->value;
            }));

        $useCase = new UpdateShipmentStatus($shipmentRepositoryMock, $outboxRepositoryMock);
        $useCase->execute('ship_123', ShipmentStatus::InTransit);
    }

    public function testExecuteThrowsExceptionIfShipmentNotFound(): void
    {
        $shipmentRepositoryMock = $this->createMock(ShipmentRepositoryInterface::class);
        $outboxRepositoryMock = $this->createMock(OutboxRepositoryInterface::class);

        $shipmentRepositoryMock->expects($this->once())
            ->method('findById')
            ->with('ship_123')
            ->willReturn(null);

        $shipmentRepositoryMock->expects($this->never())->method('save');
        $outboxRepositoryMock->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Shipment with ID ship_123 not found.");

        $useCase = new UpdateShipmentStatus($shipmentRepositoryMock, $outboxRepositoryMock);
        $useCase->execute('ship_123', ShipmentStatus::InTransit);
    }

    public function testExecuteThrowsExceptionIfShipmentInTerminalState(): void
    {
        $shipmentRepositoryMock = $this->createMock(ShipmentRepositoryInterface::class);
        $outboxRepositoryMock = $this->createMock(OutboxRepositoryInterface::class);

        $shipment = new Shipment(
            id: 'ship_123',
            sku: 'SKU-001',
            quantity: 1,
            destinationAddress: '123 Main St',
            carrier: 'UPS',
            trackingNumber: '1Z9999999999999999',
            labelUrl: 'http://example.com/label.pdf',
            shippingRateCents: 1000,
            status: ShipmentStatus::Delivered,
            createdAt: new DateTimeImmutable()
        );

        $shipmentRepositoryMock->expects($this->once())
            ->method('findById')
            ->with('ship_123')
            ->willReturn($shipment);

        $shipmentRepositoryMock->expects($this->never())->method('save');
        $outboxRepositoryMock->expects($this->never())->method('save');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot transition status from terminal state: delivered");

        $useCase = new UpdateShipmentStatus($shipmentRepositoryMock, $outboxRepositoryMock);
        $useCase->execute('ship_123', ShipmentStatus::Failed);
    }
}
