<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Returns\ValueObjects;

use InvalidArgumentException;
use InventoryApp\Domain\Returns\ValueObjects\RMANumber;
use PHPUnit\Framework\TestCase;

class RMANumberTest extends TestCase
{
    public function testCanInstantiateValidRMANumber(): void
    {
        $rmaNumber = new RMANumber('RMA-2026-0001');

        $this->assertEquals('RMA-2026-0001', $rmaNumber->getValue());
        $this->assertEquals('RMA-2026-0001', (string) $rmaNumber);
    }

    public function testTrimsWhitespace(): void
    {
        $rmaNumber = new RMANumber("  \t RMA-999 \n ");

        $this->assertEquals('RMA-999', $rmaNumber->getValue());
    }

    public function testThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RMA number cannot be empty.');

        new RMANumber('');
    }

    public function testThrowsOnWhitespaceOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RMA number cannot be empty.');

        new RMANumber('     ');
    }

    public function testThrowsWhenExceedingMaxLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RMA number cannot exceed 64 characters.');

        new RMANumber(str_repeat('A', 65));
    }

    public function testEqualsComparison(): void
    {
        $rma1 = new RMANumber('RMA-100');
        $rma2 = new RMANumber('RMA-100');
        $rma3 = new RMANumber('RMA-200');

        $this->assertTrue($rma1->equals($rma2));
        $this->assertFalse($rma1->equals($rma3));
    }
}
