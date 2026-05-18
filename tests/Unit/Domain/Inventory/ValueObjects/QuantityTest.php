<?php

namespace Tests\Unit\Domain\Inventory\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\Exceptions\InvalidQuantityException;

class QuantityTest extends TestCase
{
    public function testValidQuantityCanBeCreated(): void
    {
        $quantity = new Quantity(10);
        $this->assertEquals(10, $quantity->getValue());
    }

    public function testZeroQuantityCanBeCreated(): void
    {
        $quantity = new Quantity(0);
        $this->assertEquals(0, $quantity->getValue());
    }

    public function testNegativeQuantityThrowsException(): void
    {
        $this->expectException(InvalidQuantityException::class);
        new Quantity(-1);
    }

    public function testQuantitiesCanBeAdded(): void
    {
        $q1 = new Quantity(10);
        $q2 = new Quantity(5);
        
        $result = $q1->add($q2);
        
        $this->assertEquals(15, $result->getValue());
        $this->assertNotSame($q1, $result); // Immutability check
    }

    public function testQuantitiesCanBeSubtracted(): void
    {
        $q1 = new Quantity(10);
        $q2 = new Quantity(4);
        
        $result = $q1->subtract($q2);
        
        $this->assertEquals(6, $result->getValue());
    }

    public function testSubtractingResultingInNegativeThrowsException(): void
    {
        $q1 = new Quantity(10);
        $q2 = new Quantity(15);
        
        $this->expectException(InvalidQuantityException::class);
        $q1->subtract($q2);
    }

    public function testIsGreaterThanOrEqual(): void
    {
        $q1 = new Quantity(10);
        $q2 = new Quantity(5);
        $q3 = new Quantity(10);
        
        $this->assertTrue($q1->isGreaterThanOrEqual($q2));
        $this->assertTrue($q1->isGreaterThanOrEqual($q3));
        $this->assertFalse($q2->isGreaterThanOrEqual($q1));
    }
}
