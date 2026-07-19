<?php

namespace InventoryApp\Application\Shipping\UseCases;

use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use DateTimeImmutable;
use Exception;

class UpdateShipmentStatus
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly OutboxRepositoryInterface $outboxRepository
    ) {}

    public function execute(string $shipmentId, ShipmentStatus $status): void
    {
        $shipment = $this->shipmentRepository->findById($shipmentId);
        if (!$shipment) {
            throw new Exception("Shipment with ID {$shipmentId} not found.");
        }

        $shipment->updateStatus($status);
        $this->shipmentRepository->save($shipment);

        // Write status update outbox event
        $this->outboxRepository->save(new \InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent(
            shipmentId: $shipmentId,
            trackingNumber: $shipment->trackingNumber,
            status: $status->value
        ));
    }
}
