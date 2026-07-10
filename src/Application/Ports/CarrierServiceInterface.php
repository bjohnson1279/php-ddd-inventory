<?php

namespace InventoryApp\Application\Ports;

interface CarrierServiceInterface
{
    /**
     * @return CarrierRate[]
     */
    public function fetchRates(string $sku, int $quantity, string $destinationAddress, ?string $originLocationId = null): array;

    public function generateLabel(string $sku, int $quantity, string $destinationAddress, string $carrier, ?string $originLocationId = null): LabelResult;
}
