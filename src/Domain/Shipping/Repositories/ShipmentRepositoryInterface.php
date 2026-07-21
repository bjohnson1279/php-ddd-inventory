<?php

namespace InventoryApp\Domain\Shipping\Repositories;

use InventoryApp\Domain\Shipping\Aggregates\Shipment;

interface ShipmentRepositoryInterface
{
    public function save(Shipment $shipment): void;
    public function findById(string $id): ?Shipment;
    public function findAll(): array;
}
