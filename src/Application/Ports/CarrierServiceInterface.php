<?php

namespace InventoryApp\Application\Ports;

interface CarrierServiceInterface
{
    /**
     * @return CarrierRate[]
     */
    public function fetchRates(string $sku, int $quantity, string $destinationAddress): array;

    public function generateLabel(string $sku, int $quantity, string $destinationAddress, string $carrier): LabelResult;
}
