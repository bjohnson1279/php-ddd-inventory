<?php

namespace Tests\Unit\Domain\Catalog\Entities;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Catalog\Entities\Variant;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;

class VariantTest extends TestCase
{
    public function testCanCreateVariant(): void
    {
        $variant = new Variant(
            'v1',
            'p1',
            new SKU('TEE-L-RED'),
            ['size' => 'L', 'color' => 'Red'],
            29.99
        );

        $this->assertEquals('v1', $variant->getId());
        $this->assertEquals('p1', $variant->getProductId());
        $this->assertEquals('TEE-L-RED', $variant->getSku()->getValue());
        $this->assertEquals(['size' => 'L', 'color' => 'Red'], $variant->getAttributes());
        $this->assertEquals(29.99, $variant->getPrice());
    }

    public function testVariantCanHaveZeroPrice(): void
    {
        $variant = new Variant('v1', 'p1', new SKU('SAMPLE-ITEM'), [], 0.0);
        $this->assertEquals(0.0, $variant->getPrice());
    }

    public function testVariantCanHaveEmptyAttributes(): void
    {
        $variant = new Variant('v1', 'p1', new SKU('BASIC-SKU'), [], 9.99);
        $this->assertIsArray($variant->getAttributes());
        $this->assertEmpty($variant->getAttributes());
    }
}
