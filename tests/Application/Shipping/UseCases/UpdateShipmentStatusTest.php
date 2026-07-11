<?php

declare(strict_types=1);

namespace Tests\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\UpdateShipmentStatus;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent;
use DateTimeImmutable;
use Exception;
use DomainException;

/** @group unit */
final class UpdateShipmentStatusTest extends TestCase
{
    private ShipmentRepositoryInterface $shipmentRepository;
    private OutboxRepositoryInterface $outboxRepository;
    private UpdateShipmentStatus $useCase;

    protected function setUp(): void
    {
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->outboxRepository = $this->createMock(OutboxRepositoryInterface::class);

        $this->useCase = new UpdateShipmentStatus(
            $this->shipmentRepository,
            $this->outboxRepository
        );
    }

    public function testThrowsExceptionWhenShipmentNotFound(): void
    {
        $shipmentId = 'SHIP-123';

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with($shipmentId)
            ->willReturn(null);

        $this->shipmentRepository->expects($this->never())->method('save');
        $this->outboxRepository->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Shipment with ID {$shipmentId} not found.");

        $this->useCase->execute($shipmentId, ShipmentStatus::InTransit);
    }

    public function testThrowsExceptionWhenUpdatingFromTerminalState(): void
    {
        $shipmentId = 'SHIP-123';
        $shipment = new Shipment(
            id: $shipmentId,
            sku: 'SKU-1',
            quantity: 1,
            destinationAddress: '123 Main St',
            carrier: 'UPS',
            trackingNumber: 'TRACK-123',
            labelUrl: 'http://example.com/label',
            shippingRateCents: 1500,
            status: ShipmentStatus::Delivered, // Terminal state
            createdAt: new DateTimeImmutable()
        );

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with($shipmentId)
            ->willReturn($shipment);

        $this->shipmentRepository->expects($this->never())->method('save');
        $this->outboxRepository->expects($this->never())->method('save');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot transition status from terminal state: delivered");

        $this->useCase->execute($shipmentId, ShipmentStatus::InTransit);
    }

    public function testSuccessfulStatusUpdate(): void
    {
        $shipmentId = 'SHIP-123';
        $trackingNumber = 'TRACK-123';

        $shipment = new Shipment(
            id: $shipmentId,
            sku: 'SKU-1',
            quantity: 1,
            destinationAddress: '123 Main St',
            carrier: 'UPS',
            trackingNumber: $trackingNumber,
            labelUrl: 'http://example.com/label',
            shippingRateCents: 1500,
            status: ShipmentStatus::LabelGenerated,
            createdAt: new DateTimeImmutable()
        );

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with($shipmentId)
            ->willReturn($shipment);

        $this->shipmentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Shipment $savedShipment) {
                return $savedShipment->getStatus() === ShipmentStatus::InTransit;
            }));

        $this->outboxRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($event) use ($shipmentId, $trackingNumber) {
                return $event instanceof ShipmentStatusUpdatedEvent
                    && $event->shipmentId === $shipmentId
                    && $event->trackingNumber === $trackingNumber
                    && $event->status === ShipmentStatus::InTransit->value;
            }));

        $this->useCase->execute($shipmentId, ShipmentStatus::InTransit);
    }
}
