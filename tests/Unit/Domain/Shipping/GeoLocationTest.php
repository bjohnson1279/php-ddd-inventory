<?php

namespace Tests\Unit\Domain\Shipping;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Shipping\ValueObjects\GeoLocation;
use InvalidArgumentException;

class GeoLocationTest extends TestCase
{
    public function testShouldCreateValidGeoLocation()
    {
        $geo = new GeoLocation(40.7128, -74.0060);
        $this->assertEquals(40.7128, $geo->getLatitude());
        $this->assertEquals(-74.0060, $geo->getLongitude());
    }

    public function testShouldThrowExceptionForInvalidLatitude()
    {
        $this->expectException(InvalidArgumentException::class);
        new GeoLocation(-95.0, 0.0);
    }

    public function testShouldThrowExceptionForInvalidLongitude()
    {
        $this->expectException(InvalidArgumentException::class);
        new GeoLocation(0.0, 185.0);
    }

    public function testShouldComputeDistanceAccuratelyUsingHaversine()
    {
        $ny = new GeoLocation(40.7128, -74.0060);
        $la = new GeoLocation(34.0522, -118.2437);

        $dist = $ny->distanceTo($la);
        $this->assertGreaterThan(3900, $dist);
        $this->assertLessThan(4000, $dist);
    }

    public function testShouldComputeZeroDistanceForSameLocation()
    {
        $geo = new GeoLocation(40.7128, -74.0060);
        $this->assertEquals(0.0, $geo->distanceTo($geo));
    }
}
