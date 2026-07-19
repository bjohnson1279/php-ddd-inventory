<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use InventoryApp\Application\Shipping\UseCases\UpdateShipmentStatus;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Exception;
use DomainException;
use DateTimeImmutable;

class UpdateShipmentStatusTest extends TestCase
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

    private function createShipment(ShipmentStatus $status = ShipmentStatus::LabelGenerated): Shipment
    {
        return new Shipment(
            id: 'ship-123',
            sku: 'SKU-TEST',
            quantity: 1,
            destinationAddress: '123 Test St',
            carrier: 'FedEx',
            trackingNumber: 'TRK-987654321',
            labelUrl: 'http://example.com/label',
            shippingRateCents: 1500,
            status: $status,
            createdAt: new DateTimeImmutable('2023-01-01 12:00:00')
        );
    }

    public function testExecuteUpdatesStatusAndSavesOutboxEvent(): void
    {
        $shipment = $this->createShipment();
        $newStatus = ShipmentStatus::InTransit;

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with('ship-123')
            ->willReturn($shipment);

        $this->shipmentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Shipment $savedShipment) use ($newStatus) {
                return $savedShipment->getStatus() === $newStatus;
            }));

        $this->outboxRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($event) use ($newStatus) {
                return $event instanceof ShipmentStatusUpdatedEvent
                    && $event->shipmentId === 'ship-123'
                    && $event->trackingNumber === 'TRK-987654321'
                    && $event->status === $newStatus->value;
            }));

        $this->useCase->execute('ship-123', $newStatus);
    }

    public function testExecuteThrowsExceptionWhenShipmentNotFound(): void
    {
        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with('invalid-id')
            ->willReturn(null);

        $this->shipmentRepository->expects($this->never())
            ->method('save');

        $this->outboxRepository->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Shipment with ID invalid-id not found.");

        $this->useCase->execute('invalid-id', ShipmentStatus::InTransit);
    }

    public function testExecuteThrowsDomainExceptionWhenUpdatingFromTerminalState(): void
    {
        // Setup shipment in a terminal state
        $shipment = $this->createShipment(ShipmentStatus::Delivered);

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with('ship-123')
            ->willReturn($shipment);

        $this->shipmentRepository->expects($this->never())
            ->method('save');

        $this->outboxRepository->expects($this->never())
            ->method('save');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot transition status from terminal state: delivered");

        $this->useCase->execute('ship-123', ShipmentStatus::Failed);
    }

    public function testExecuteBubblesExceptionFromShipmentRepositoryAndDoesNotSaveOutbox(): void
    {
        $shipment = $this->createShipment();
        $newStatus = ShipmentStatus::InTransit;

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with('ship-123')
            ->willReturn($shipment);

        $repositoryException = new Exception("Database connection failed");
        $this->shipmentRepository->expects($this->once())
            ->method('save')
            ->willThrowException($repositoryException);

        $this->outboxRepository->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Database connection failed");

        $this->useCase->execute('ship-123', $newStatus);
    }

    public function testExecuteBubblesExceptionFromOutboxRepository(): void
    {
        $shipment = $this->createShipment();
        $newStatus = ShipmentStatus::InTransit;

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with('ship-123')
            ->willReturn($shipment);

        $this->shipmentRepository->expects($this->once())
            ->method('save');

        $outboxException = new Exception("Outbox write failed");
        $this->outboxRepository->expects($this->once())
            ->method('save')
            ->willThrowException($outboxException);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Outbox write failed");

        $this->useCase->execute('ship-123', $newStatus);
    }
}
