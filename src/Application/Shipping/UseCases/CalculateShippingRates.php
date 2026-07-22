<?php

namespace InventoryApp\Application\Shipping\UseCases;

use InventoryApp\Application\Ports\CarrierServiceInterface;
use InvalidArgumentException;

class CalculateShippingRates
{
    public function __construct(
        private readonly CarrierServiceInterface $carrierService
    ) {}

    /**
     * @return \InventoryApp\Application\Ports\CarrierRate[]
     */
    public function execute(string $sku, int $quantity, string $destinationAddress): array
    {
        if (trim($sku) === '' || trim($destinationAddress) === '') {
            throw new InvalidArgumentException("Missing required rate fields: sku and destinationAddress.");
        }

        return $this->carrierService->fetchRates($sku, $quantity, $destinationAddress);
    }
}
