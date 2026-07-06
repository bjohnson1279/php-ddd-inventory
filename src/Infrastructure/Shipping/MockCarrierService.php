<?php

namespace InventoryApp\Infrastructure\Shipping;

use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Application\Ports\CarrierRate;
use InventoryApp\Application\Ports\LabelResult;

class MockCarrierService implements CarrierServiceInterface
{
    public function fetchRates(string $sku, int $quantity, string $destinationAddress): array
    {
        $weightFactor = (strlen($sku) % 3) + 1;
        $baseQuantity = $quantity ?: 1;

        return [
            new CarrierRate(
                'UPS Ground',
                (int)ceil((500 + ($weightFactor * 50)) * $baseQuantity),
                4
            ),
            new CarrierRate(
                'FedEx Express',
                (int)ceil((1500 + ($weightFactor * 100)) * $baseQuantity),
                1
            ),
            new CarrierRate(
                'DHL Worldwide',
                (int)ceil((3500 + ($weightFactor * 250)) * $baseQuantity),
                3
            ),
            new CarrierRate(
                'USPS Priority',
                (int)ceil((450 + ($weightFactor * 35)) * $baseQuantity),
                5
            )
        ];
    }

    public function generateLabel(string $sku, int $quantity, string $destinationAddress, string $carrier): LabelResult
    {
        $rates = $this->fetchRates($sku, $quantity, $destinationAddress);
        
        $selectedRate = null;
        foreach ($rates as $rate) {
            if (strcasecmp($rate->carrier, $carrier) === 0) {
                $selectedRate = $rate;
                break;
            }
        }
        
        if ($selectedRate === null) {
            $selectedRate = $rates[0];
        }

        $randomSuffix = random_int(100000, 999999);
        $trackingNumber = "TRACK-{$randomSuffix}";

        $carrierLower = strtolower($carrier);
        if (str_contains($carrierLower, 'ups')) {
            $trackingNumber = "1Z999AA1012345{$randomSuffix}";
        } elseif (str_contains($carrierLower, 'fedex')) {
            $trackingNumber = "99{$randomSuffix}7712";
        } elseif (str_contains($carrierLower, 'usps')) {
            $trackingNumber = "94001118995632{$randomSuffix}";
        }

        $labelUrl = "https://shipping-labels.s3.amazonaws.com/labels/label_{$trackingNumber}.pdf";

        return new LabelResult($trackingNumber, $labelUrl, $selectedRate->rateCents);
    }
}
