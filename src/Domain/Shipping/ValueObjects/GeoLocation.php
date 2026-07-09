<?php

namespace InventoryApp\Domain\Shipping\ValueObjects;

use InvalidArgumentException;

class GeoLocation
{
    private float $latitude;
    private float $longitude;

    public function __construct(float $latitude, float $longitude)
    {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Latitude must be a float between -90 and 90");
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Longitude must be a float between -180 and 180");
        }
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * Calculates the distance to another GeoLocation in kilometers using the Haversine formula.
     */
    public function distanceTo(GeoLocation $other): float
    {
        $R = 6371; // Earth's radius in kilometers
        $dLat = deg2rad($other->getLatitude() - $this->latitude);
        $dLon = deg2rad($other->getLongitude() - $this->longitude);
        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->getLatitude());

        $a = sin($dLat / 2) * sin($dLat / 2) +
             sin($dLon / 2) * sin($dLon / 2) * cos($lat1) * cos($lat2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    public function equals(GeoLocation $other): bool
    {
        return $this->latitude === $other->getLatitude() && $this->longitude === $other->getLongitude();
    }
}
