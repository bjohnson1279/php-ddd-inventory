<?php

namespace Tests\Unit\Domain\Inventory\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use InvalidArgumentException;

class ConditionTest extends TestCase
{
    public function testValidConditionsCanBeCreated(): void
    {
        $this->assertEquals(Condition::NEW, (new Condition(Condition::NEW))->getValue());
        $this->assertEquals(Condition::OPEN_BOX, (new Condition(Condition::OPEN_BOX))->getValue());
        $this->assertEquals(Condition::DAMAGED, (new Condition(Condition::DAMAGED))->getValue());
    }

    public function testInvalidConditionThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Condition('refurbished');
    }

    public function testConditionEquality(): void
    {
        $a = new Condition(Condition::NEW);
        $b = new Condition(Condition::NEW);
        $c = new Condition(Condition::DAMAGED);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToStringReturnsValue(): void
    {
        $condition = new Condition(Condition::OPEN_BOX);
        $this->assertEquals(Condition::OPEN_BOX, (string) $condition);
    }
}
