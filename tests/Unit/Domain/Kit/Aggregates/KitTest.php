<?php

namespace Tests\Unit\Domain\Kit\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Kit\Aggregates\Kit;

class KitTest extends TestCase
{
    public function testInitialKitIsEmpty(): void
    {
        $kit = new Kit('k-1', 'KIT-SKU', 'Test Kit');
        $this->assertTrue($kit->isEmpty());
        $this->assertEmpty($kit->components());
    }

    public function testAddComponent(): void
    {
        $kit = new Kit('k-1', 'KIT-SKU', 'Test Kit');
        $kit->addComponent('v-1', 2);

        $this->assertFalse($kit->isEmpty());
        $this->assertCount(1, $kit->components());
        $this->assertEquals('v-1', $kit->components()[0]->variantId);
        $this->assertEquals(2, $kit->components()[0]->quantity);
    }

    public function testAddingSameComponentUpdatesQuantity(): void
    {
        $kit = new Kit('k-1', 'KIT-SKU', 'Test Kit');
        $kit->addComponent('v-1', 2);
        $kit->addComponent('v-1', 3);

        $this->assertCount(1, $kit->components());
        $this->assertEquals(5, $kit->components()[0]->quantity);
    }

    public function testAddMultipleComponents(): void
    {
        $kit = new Kit('k-1', 'KIT-SKU', 'Test Kit');
        $kit->addComponent('v-1', 2);
        $kit->addComponent('v-2', 1);

        $this->assertCount(2, $kit->components());
    }
}
