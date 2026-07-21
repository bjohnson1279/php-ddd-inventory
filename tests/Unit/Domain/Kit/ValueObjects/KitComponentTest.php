<?php

namespace Tests\Unit\Domain\Kit\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Kit\ValueObjects\KitComponent;
use InvalidArgumentException;

class KitComponentTest extends TestCase
{
    public function testValidComponent(): void
    {
        $component = new KitComponent('v-1', 5);
        $this->assertEquals('v-1', $component->variantId);
        $this->assertEquals(5, $component->quantity);
    }

    public function testInvalidQuantityThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new KitComponent('v-1', 0);
    }

    public function testNegativeQuantityThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new KitComponent('v-1', -1);
    }
}
