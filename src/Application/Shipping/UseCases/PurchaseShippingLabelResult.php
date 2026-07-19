<?php

namespace InventoryApp\Application\Shipping\UseCases;

class PurchaseShippingLabelResult
{
    public function __construct(
        public readonly string $shipmentId,
        public readonly string $trackingNumber,
        public readonly string $labelUrl,
        public readonly int $rateCents
    ) {}

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
