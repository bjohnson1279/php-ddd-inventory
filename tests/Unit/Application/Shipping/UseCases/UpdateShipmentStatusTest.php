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
    private $shipmentRepositoryMock;
    private $outboxRepositoryMock;
    private $useCase;

    protected function setUp(): void
    {
        $this->shipmentRepositoryMock = $this->createMock(ShipmentRepositoryInterface::class);
        $this->outboxRepositoryMock = $this->createMock(OutboxRepositoryInterface::class);

        $this->useCase = new UpdateShipmentStatus(
            $this->shipmentRepositoryMock,
            $this->outboxRepositoryMock
        );
    }

    public function testExecuteThrowsExceptionIfShipmentNotFound()
    {
        $this->shipmentRepositoryMock->method('findById')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Shipment with ID ship-1 not found.");

        $this->shipmentRepositoryMock->expects($this->never())->method('save');
        $this->outboxRepositoryMock->expects($this->never())->method('save');

        $this->useCase->execute('ship-1', ShipmentStatus::InTransit);
    }

    public function testExecuteThrowsExceptionIfShipmentInTerminalState()
    {
        $shipment = new Shipment(
            'ship-1',
            'SKU-1',
            1,
            '123 Main St',
            'FedEx',
            'TRK123',
            'url',
            1000,
            ShipmentStatus::Delivered,
            new DateTimeImmutable()
        );

        $this->shipmentRepositoryMock->method('findById')->willReturn($shipment);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot transition status from terminal state: delivered");

        $this->shipmentRepositoryMock->expects($this->never())->method('save');
        $this->outboxRepositoryMock->expects($this->never())->method('save');

        $this->useCase->execute('ship-1', ShipmentStatus::Failed);
    }

    public function testExecuteSuccessfullyUpdatesShipmentStatus()
    {
        $shipment = new Shipment(
            'ship-1',
            'SKU-1',
            1,
            '123 Main St',
            'FedEx',
            'TRK123',
            'url',
            1000,
            ShipmentStatus::LabelGenerated,
            new DateTimeImmutable()
        );

        $this->shipmentRepositoryMock->expects($this->once())
             ->method('findById')
             ->with('ship-1')
             ->willReturn($shipment);

        $this->shipmentRepositoryMock->expects($this->once())
             ->method('save')
             ->with($this->callback(function (Shipment $s) {
                 return $s->getStatus() === ShipmentStatus::InTransit;
             }));

        $this->outboxRepositoryMock->expects($this->once())
             ->method('save')
             ->with($this->callback(function (ShipmentStatusUpdatedEvent $event) {
                 return $event->shipmentId === 'ship-1' &&
                        $event->trackingNumber === 'TRK123' &&
                        $event->status === ShipmentStatus::InTransit->value;
             }));

        $this->useCase->execute('ship-1', ShipmentStatus::InTransit);
    }
}
