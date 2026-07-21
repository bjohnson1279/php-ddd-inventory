<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use InventoryApp\Application\Shipping\UseCases\UpdateShipmentStatus;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use DateTimeImmutable;
use DomainException;
use Exception;
use PHPUnit\Framework\TestCase;

class UpdateShipmentStatusTest extends TestCase
{
    private ShipmentRepositoryInterface $shipmentRepository;
    private OutboxRepositoryInterface $outboxRepository;
    private UpdateShipmentStatus $useCase;

    protected function setUp(): void
    {
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->outboxRepository = $this->createMock(OutboxRepositoryInterface::class);
        $this->useCase = new UpdateShipmentStatus($this->shipmentRepository, $this->outboxRepository);
    }

    private function createShipment(ShipmentStatus $status = ShipmentStatus::LabelGenerated): Shipment
    {
        return new Shipment(
            id: 'SHIP-123',
            sku: 'SKU-TEST',
            quantity: 1,
            destinationAddress: '123 Test St',
            carrier: 'FedEx',
            trackingNumber: 'TRK-123456',
            labelUrl: 'http://example.com/label.pdf',
            shippingRateCents: 500,
            status: $status,
            createdAt: new DateTimeImmutable('2023-01-01 10:00:00')
        );
    }

    public function test_it_successfully_updates_shipment_status(): void
    {
        $shipment = $this->createShipment(ShipmentStatus::LabelGenerated);

        $this->shipmentRepository->expects($this->once())
            ->method('findById')
            ->with('SHIP-123')
            ->willReturn($shipment);

            ->method('save')
            ->willReturnCallback(function (Shipment $savedShipment) {
                $this->assertEquals(ShipmentStatus::InTransit, $savedShipment->getStatus());
                return $savedShipment; // just return it if needed by the mock, though return type is void
            });

        $this->outboxRepository->expects($this->once())
            ->willReturnCallback(function ($event) {
                $this->assertInstanceOf(ShipmentStatusUpdatedEvent::class, $event);
                /** @var ShipmentStatusUpdatedEvent $event */
                $this->assertEquals('SHIP-123', $event->shipmentId);
                $this->assertEquals('TRK-123456', $event->trackingNumber);
                $this->assertEquals(ShipmentStatus::InTransit->value, $event->status);

        $this->useCase->execute('SHIP-123', ShipmentStatus::InTransit);
    }

    public function test_it_throws_exception_if_shipment_not_found(): void
    {
            ->with('SHIP-404')
            ->willReturn(null);

        $this->shipmentRepository->expects($this->never())
            ->method('save');

        $this->outboxRepository->expects($this->never())

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Shipment with ID SHIP-404 not found.");

        $this->useCase->execute('SHIP-404', ShipmentStatus::InTransit);
    }

    public function test_it_throws_domain_exception_if_updating_from_terminal_state(): void
    {
        $shipment = $this->createShipment(ShipmentStatus::Delivered);




{
    private $shipmentRepositoryMock;
    private $outboxRepositoryMock;
    private $useCase;

    {
        $this->shipmentRepositoryMock = $this->createMock(ShipmentRepositoryInterface::class);
        $this->outboxRepositoryMock = $this->createMock(OutboxRepositoryInterface::class);

        $this->useCase = new UpdateShipmentStatus(
            $this->shipmentRepositoryMock,
            $this->outboxRepositoryMock
    }

    public function testExecuteThrowsExceptionIfShipmentNotFound()
    {
        $this->shipmentRepositoryMock->method('findById')->willReturn(null);

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

        $this->shipmentRepositoryMock->method('findById')->willReturn($shipment);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Cannot transition status from terminal state: delivered");

        $this->useCase->execute('SHIP-123', ShipmentStatus::Failed);
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

             ->method('save')
             ->with($this->callback(function (Shipment $s) {
                 return $s->getStatus() === ShipmentStatus::InTransit;
             }));

        $this->outboxRepositoryMock->expects($this->once())
             ->with($this->callback(function (ShipmentStatusUpdatedEvent $event) {
                 return $event->shipmentId === 'ship-1' &&
                        $event->trackingNumber === 'TRK123' &&
                        $event->status === ShipmentStatus::InTransit->value;

        $this->useCase->execute('ship-1', ShipmentStatus::InTransit);
    }
}
