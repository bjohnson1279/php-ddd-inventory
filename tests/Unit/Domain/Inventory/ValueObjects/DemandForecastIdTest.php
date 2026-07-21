<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inventory\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId;
use InvalidArgumentException;

final class DemandForecastIdTest extends TestCase
{
    public function testValidDemandForecastIdCanBeCreated(): void
    {
        $id = new DemandForecastId('uuid-123');
        $this->assertEquals('uuid-123', $id->getValue());
        $this->assertEquals('uuid-123', (string)$id);
    }

    public function testEmptyDemandForecastIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DemandForecastId('   ');
    }

    public function testDemandForecastIdEquality(): void
    {
        $id1 = new DemandForecastId('id-1');
        $id2 = new DemandForecastId('id-1');
        $id3 = new DemandForecastId('id-2');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}
