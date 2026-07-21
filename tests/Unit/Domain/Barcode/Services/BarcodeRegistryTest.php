<?php

namespace Tests\Unit\Domain\Barcode\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Barcode\Services\BarcodeRegistry;

class BarcodeRegistryTest extends TestCase
{
    public function testRegisterAndResolve()
    {
        $reg = new BarcodeRegistry();
        $reg->register('abc123', 'variant-1');
        $this->assertTrue($reg->isRegistered('abc123'));
        $this->assertEquals('variant-1', $reg->resolve('abc123'));
    }

    public function testResolveUnknownThrows()
    {
        $this->expectException(\DomainException::class);
        $reg = new BarcodeRegistry();
        $reg->resolve('no-such');
    }
}
