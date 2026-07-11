<?php

namespace InventoryApp\Application\Shipping\UseCases;

class PurchaseShippingLabelResult
{
    public function __construct(
        public readonly string $shipmentId,
        public readonly string $trackingNumber,
        public readonly string $labelUrl,
        public readonly int $rateCents
    ) {
        if (empty(trim($shipmentId))) {
            throw new \InvalidArgumentException('Shipment ID cannot be empty');
        }

        if (empty(trim($trackingNumber))) {
            throw new \InvalidArgumentException('Tracking number cannot be empty');
        }

        if (empty(trim($labelUrl))) {
            throw new \InvalidArgumentException('Label URL cannot be empty');
        }

        if ($rateCents < 0) {
            throw new \InvalidArgumentException('Rate cents cannot be negative');
        }
    }

    public function toArray(): array
    {
        return [
            'shipmentId' => $this->shipmentId,
            'trackingNumber' => $this->trackingNumber,
            'labelUrl' => $this->labelUrl,
            'rateCents' => $this->rateCents,
        ];
    }
}
