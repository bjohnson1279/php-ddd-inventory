<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use InventoryApp\Infrastructure\Models\ShipmentModel;
use DateTimeImmutable;

class EloquentShipmentRepository implements ShipmentRepositoryInterface
{
    public function save(Shipment $shipment): void
    {
        ShipmentModel::updateOrCreate(
            ['id' => $shipment->id],
            [
                'sku' => $shipment->sku,
                'quantity' => $shipment->quantity,
                'destination_address' => $shipment->destinationAddress,
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->trackingNumber,
                'label_url' => $shipment->labelUrl,
                'shipping_rate_cents' => $shipment->shippingRateCents,
                'status' => $shipment->getStatus()->value,
                'created_at' => $shipment->createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $shipment->updatedAt->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findById(string $id): ?Shipment
    {
        $model = ShipmentModel::find($id);
        if (!$model) {
            return null;
        }

        return new Shipment(
            $model->id,
            $model->sku,
            $model->quantity,
            $model->destination_address,
            $model->carrier,
            $model->tracking_number,
            $model->label_url,
            $model->shipping_rate_cents,
            ShipmentStatus::from($model->status),
            new DateTimeImmutable($model->created_at),
            new DateTimeImmutable($model->updated_at)
        );
    }

    public function findAll(): array
    {
        $models = ShipmentModel::orderBy('created_at', 'desc')->get();
        $shipments = [];
        foreach ($models as $model) {
            $shipments[] = new Shipment(
                $model->id,
                $model->sku,
                $model->quantity,
                $model->destination_address,
                $model->carrier,
                $model->tracking_number,
                $model->label_url,
                $model->shipping_rate_cents,
                ShipmentStatus::from($model->status),
                new DateTimeImmutable($model->created_at),
                new DateTimeImmutable($model->updated_at)
            );
        }
        return $shipments;
    }
}
