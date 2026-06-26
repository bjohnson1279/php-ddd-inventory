<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inventory\Entities;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Entities\DemandForecast;
use InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;
use InvalidArgumentException;

final class DemandForecastTest extends TestCase
{
    public function testDemandForecastCreation(): void
    {
        $id = new DemandForecastId('forecast-1');
        $sku = new SKU('SKU-TEST');
        $loc = new LocationId('LOC-INT');
        $start = new DateTimeImmutable('2026-06-01 00:00:00');
        $end = new DateTimeImmutable('2026-06-15 00:00:00');
        $now = new DateTimeImmutable();

        $forecast = new DemandForecast(
            $id,
            $sku,
            $loc,
            100,
            $start,
            $end,
            0.85,
            $now
        );

        $this->assertTrue($id->equals($forecast->id));
        $this->assertTrue($sku->equals($forecast->sku));
        $this->assertTrue($loc->equals($forecast->locationId));
        $this->assertEquals(100, $forecast->forecastedQuantity);
        $this->assertEquals($start, $forecast->periodStart);
        $this->assertEquals($end, $forecast->periodEnd);
        $this->assertEquals(0.85, $forecast->confidenceLevel);
        $this->assertEquals($now, $forecast->createdAt);
    }

    public function testNegativeQuantityThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DemandForecast(
            new DemandForecastId('forecast-1'),
            new SKU('SKU-TEST'),
            new LocationId('LOC-INT'),
            -5,
            new DateTimeImmutable('2026-06-01 00:00:00'),
            new DateTimeImmutable('2026-06-15 00:00:00'),
            0.85,
            new DateTimeImmutable()
        );
    }

    public function testInvalidConfidenceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DemandForecast(
            new DemandForecastId('forecast-1'),
            new SKU('SKU-TEST'),
            new LocationId('LOC-INT'),
            100,
            new DateTimeImmutable('2026-06-01 00:00:00'),
            new DateTimeImmutable('2026-06-15 00:00:00'),
            1.5,
            new DateTimeImmutable()
        );
    }

    public function testInvalidPeriodThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DemandForecast(
            new DemandForecastId('forecast-1'),
            new SKU('SKU-TEST'),
            new LocationId('LOC-INT'),
            100,
            new DateTimeImmutable('2026-06-15 00:00:00'),
            new DateTimeImmutable('2026-06-01 00:00:00'),
            0.85,
            new DateTimeImmutable()
        );
    }
}
