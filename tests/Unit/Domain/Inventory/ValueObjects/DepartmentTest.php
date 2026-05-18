<?php

namespace Tests\Unit\Domain\Inventory\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InvalidArgumentException;

class DepartmentTest extends TestCase
{
    public function testValidDepartmentCanBeCreated(): void
    {
        $dept = new Department('APPAREL');
        $this->assertEquals('APPAREL', $dept->getValue());
    }

    public function testDepartmentTrimsWhitespace(): void
    {
        $dept = new Department('  APPAREL  ');
        $this->assertEquals('APPAREL', $dept->getValue());
    }

    public function testEmptyDepartmentThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Department('');
    }

    public function testWhitespaceOnlyDepartmentThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Department('   ');
    }

    public function testDepartmentEquality(): void
    {
        $a = new Department('APPAREL');
        $b = new Department('APPAREL');
        $c = new Department('ELECTRONICS');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToStringReturnsValue(): void
    {
        $dept = new Department('FOOTWEAR');
        $this->assertEquals('FOOTWEAR', (string) $dept);
    }
}
