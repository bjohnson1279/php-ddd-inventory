<?php

namespace InventoryApp\Application\Ports;

class CarrierRate
{
    public function __construct(
        public readonly string $carrier,
        public readonly int $rateCents,
        public readonly int $estimatedDays
    ) {}

    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'rateCents' => $this->rateCents,
            'estimatedDays' => $this->estimatedDays,
        ];
    }
}
