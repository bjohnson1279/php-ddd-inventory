<?php

namespace Tests\Unit\Domain\Inventory\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\Exceptions\InvalidSKUException;

class SKUTest extends TestCase
{
    public function testValidSKUCanBeCreated(): void
    {
        $sku = new SKU('PROD-123');
        $this->assertEquals('PROD-123', $sku->getValue());
    }

    public function testSKUIsAlwaysUppercase(): void
    {
        $sku = new SKU('prod-123');
        $this->assertEquals('PROD-123', $sku->getValue());
    }

    public function testSKUEquality(): void
    {
        $sku1 = new SKU('PROD-123');
        $sku2 = new SKU('PROD-123');
        $sku3 = new SKU('PROD-456');

        $this->assertTrue($sku1->equals($sku2));
        $this->assertFalse($sku1->equals($sku3));
    }

    public function testEmptySKUThrowsException(): void
    {
        $this->expectException(InvalidSKUException::class);
        new SKU('');
    }

    public function testTooShortSKUThrowsException(): void
    {
        $this->expectException(InvalidSKUException::class);
        new SKU('AB'); // Minimum is 3
    }

    public function testInvalidCharactersSKUThrowsException(): void
    {
        $this->expectException(InvalidSKUException::class);
        new SKU('PROD@123'); // Only alphanumeric and dashes allowed
    }
}
