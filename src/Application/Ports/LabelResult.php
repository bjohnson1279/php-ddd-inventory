<?php

namespace InventoryApp\Application\Ports;

class LabelResult
{
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $labelUrl,
        public readonly int $rateCents
    ) {}

    public function toArray(): array
    {
        return [
            'trackingNumber' => $this->trackingNumber,
            'labelUrl' => $this->labelUrl,
            'rateCents' => $this->rateCents,
        ];
    }
}
