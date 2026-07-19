<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\UpdateShipmentStatus;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent;
use DateTimeImmutable;
use Exception;

class UpdateShipmentStatusTest extends TestCase
{
    private $shipmentRepository;
    private $outboxRepository;
    private $useCase;

    protected function setUp(): void
    {
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->outboxRepository = $this->createMock(OutboxRepositoryInterface::class);
        $this->useCase = new UpdateShipmentStatus($this->shipmentRepository, $this->outboxRepository);
    }

    public function testExecuteSuccessfullyUpdatesStatusAndSavesEvent(): void
    {
        $shipmentId = 'SHIP-123';
        $trackingNumber = 'TRACK-ABC';
        $shipment = new Shipment(
            id: $shipmentId,
            sku: 'SKU-XYZ',
            quantity: 10,
            destinationAddress: '123 Main St',
            carrier: 'UPS',
            trackingNumber: $trackingNumber,
            labelUrl: null,
            shippingRateCents: 1500,
            status: ShipmentStatus::LabelGenerated,
            createdAt: new DateTimeImmutable()
        );

        $newStatus = ShipmentStatus::InTransit;

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with($shipmentId)
            ->willReturn($shipment);

        $this->shipmentRepository->expects($this->once())
            ->method('save')
            ->with($shipment);

        $this->outboxRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($event) use ($shipmentId, $trackingNumber, $newStatus) {
                return $event instanceof ShipmentStatusUpdatedEvent
                    && $event->shipmentId === $shipmentId
                    && $event->trackingNumber === $trackingNumber
                    && $event->status === $newStatus->value;
            }));

        $this->useCase->execute($shipmentId, $newStatus);

        $this->assertEquals($newStatus, $shipment->getStatus());
    }

    public function testExecuteThrowsExceptionWhenShipmentNotFound(): void
    {
        $shipmentId = 'NON-EXISTENT-SHIPMENT';
        $newStatus = ShipmentStatus::InTransit;

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with($shipmentId)
            ->willReturn(null);

        $this->shipmentRepository->expects($this->never())
            ->method('save');

        $this->outboxRepository->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Shipment with ID {$shipmentId} not found.");

        $this->useCase->execute($shipmentId, $newStatus);
    }
}
