<?php

namespace Tests\Unit\Domain\Inventory\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InvalidArgumentException;

class LocationIdTest extends TestCase
{
    public function testValidLocationIdCanBeCreated(): void
    {
        $loc = new LocationId('LOC-STOREFRONT');
        $this->assertEquals('LOC-STOREFRONT', $loc->getValue());
    }

    public function testLocationIdTrimsWhitespace(): void
    {
        $loc = new LocationId('  LOC-BACKROOM  ');
        $this->assertEquals('LOC-BACKROOM', $loc->getValue());
    }

    public function testEmptyLocationIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LocationId('');
    }

    public function testWhitespaceOnlyLocationIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LocationId('   ');
    }

    public function testLocationIdEquality(): void
    {
        $a = new LocationId('LOC-STOREFRONT');
        $b = new LocationId('LOC-STOREFRONT');
        $c = new LocationId('LOC-BACKROOM');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToStringReturnsValue(): void
    {
        $loc = new LocationId('LOC-STOREFRONT');
        $this->assertEquals('LOC-STOREFRONT', (string) $loc);
    }
}
