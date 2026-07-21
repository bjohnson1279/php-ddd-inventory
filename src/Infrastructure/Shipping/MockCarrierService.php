<?php

namespace InventoryApp\Infrastructure\Shipping;

use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Application\Ports\CarrierRate;
use InventoryApp\Application\Ports\LabelResult;

class MockCarrierService implements CarrierServiceInterface
{
    private function getDistance(string $origin, string $destination): float
    {
        $org = strtoupper($origin);
        $dest = strtolower($destination);

        $baseDist = 1000.0;
        if (str_contains($org, "EAST") && (str_contains($dest, "ny") || str_contains($dest, "new york") || str_contains($dest, "10001"))) {
            $baseDist = 100.0;
        } elseif (str_contains($org, "WEST") && (str_contains($dest, "la") || str_contains($dest, "los angeles") || str_contains($dest, "ca") || str_contains($dest, "90210"))) {
            $baseDist = 100.0;
        } elseif (str_contains($org, "CENTRAL") && (str_contains($dest, "chicago") || str_contains($dest, "il") || str_contains($dest, "60601"))) {
            $baseDist = 100.0;
        } elseif (str_contains($org, "EAST") && (str_contains($dest, "la") || str_contains($dest, "ca") || str_contains($dest, "90210"))) {
            $baseDist = 4000.0;
        } elseif (str_contains($org, "WEST") && (str_contains($dest, "ny") || str_contains($dest, "new york") || str_contains($dest, "10001"))) {
            $baseDist = 4000.0;
        }

        return $baseDist;
    }

    public function fetchRates(string $sku, int $quantity, string $destinationAddress, ?string $originLocationId = null): array
    {
        $weightFactor = (strlen($sku) % 3) + 1;
        $baseQuantity = $quantity ?: 1;

        $distanceKm = $this->getDistance($originLocationId ?? "default", $destinationAddress);
        $distanceCost = (int)ceil($distanceKm * 0.1);

        return [
            new CarrierRate(
                'UPS Ground',
                (int)ceil((500 + ($weightFactor * 50) + $distanceCost) * $baseQuantity),
                $distanceKm > 2000 ? 5 : 2
            ),
            new CarrierRate(
                'FedEx Express',
                (int)ceil((1500 + ($weightFactor * 100) + $distanceCost * 1.5) * $baseQuantity),
                1
            ),
            new CarrierRate(
                'DHL Worldwide',
                (int)ceil((3500 + ($weightFactor * 250) + $distanceCost * 2) * $baseQuantity),
                $distanceKm > 2000 ? 3 : 1
            ),
            new CarrierRate(
                'USPS Priority',
                (int)ceil((450 + ($weightFactor * 35) + $distanceCost * 0.8) * $baseQuantity),
                $distanceKm > 2000 ? 6 : 3
            )
        ];
    }

    public function generateLabel(string $sku, int $quantity, string $destinationAddress, string $carrier, ?string $originLocationId = null): LabelResult
    {
        $rates = $this->fetchRates($sku, $quantity, $destinationAddress, $originLocationId);

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
