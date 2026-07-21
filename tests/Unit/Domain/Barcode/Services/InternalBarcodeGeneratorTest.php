<?php

namespace Tests\Unit\Domain\Barcode\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Barcode\Services\BarcodeRegistry;
use InventoryApp\Domain\Barcode\Services\InternalBarcodeGenerator;
use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;

class InternalBarcodeGeneratorTest extends TestCase
{
    public function testGeneratesUniqueBarcode()
    {
        $registry = new BarcodeRegistry();
        $generator = new InternalBarcodeGenerator($registry);

        $barcode = $generator->generate('variant-1', 'tenant-1');
        
        $this->assertEquals(BarcodeSymbology::CODE_128, $barcode->symbology);
        $this->assertStringStartsWith('INV-', $barcode->value);
        $this->assertEquals(17, strlen($barcode->value)); // INV + '-' + 4 chars + '-' + 8 chars = 17 chars
    }

    public function testAvoidsCollisions()
    {
        // Register the first generated barcode value manually
        $registry->register('INV-52F3-C264D055', 'some-other-variant'); // This is the hash of variant-1 and tenant-1 with salt 0


        // Should bypass salt 0 (collision) and succeed with salt 1 (different value)
        $this->assertNotEquals('INV-52F3-C264D055', $barcode->value);
    }
}



{
    {

        
    }

    {
        $registry->register('INV-9958-9CF83C66', 'some-other-variant'); // This is the hash of variant-1 and tenant-1 with salt 0


        $this->assertNotEquals('INV-9958-9CF83C66', $barcode->value);
    }
}
