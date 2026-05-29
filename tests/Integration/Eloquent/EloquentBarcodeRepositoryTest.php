<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentBarcodeRepository;
use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSource;
use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;
use InventoryApp\Domain\Barcode\Aggregates\VariantBarcodeSet;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentBarcodeRepositoryTest extends TestCase
{
    private EloquentBarcodeRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new EloquentBarcodeRepository();
    }

    public function test_register_assignment_and_find_variant(): void
    {
        $variantId = uuidv4();
        $barcode = new Barcode(BarcodeSymbology::UPC_A, '012345678905');

        $this->repo->registerAssignment($variantId, $barcode, BarcodeSource::Internal, true);

        $foundVariant = $this->repo->findVariantByBarcodeValue('012345678905');
        $this->assertEquals($variantId, $foundVariant);

        // Case insensitivity test
        $foundVariantUpper = $this->repo->findVariantByBarcodeValue('012345678905');
        $this->assertEquals($variantId, $foundVariantUpper);
    }

    public function test_register_duplicate_barcode_throws_exception(): void
    {
        $variantId1 = uuidv4();
        $variantId2 = uuidv4();
        $barcode = new Barcode(BarcodeSymbology::EAN_13, '9780201379624');

        $this->repo->registerAssignment($variantId1, $barcode, BarcodeSource::Internal, true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Barcode value already registered');

        $this->repo->registerAssignment($variantId2, $barcode, BarcodeSource::Internal, false);
    }

    public function test_save_and_find_set_for_variant(): void
    {
        $variantId = uuidv4();
        $set = new VariantBarcodeSet($variantId);

        $barcode1 = new Barcode(BarcodeSymbology::EAN_8, '12345670');
        $barcode2 = new Barcode(BarcodeSymbology::CODE_128, 'TEST-BARCODE-128');

        $set->assign($barcode1, BarcodeSource::Internal, true);
        $set->assign($barcode2, BarcodeSource::Supplier, false);

        $this->repo->saveSet($set);

        $loadedSet = $this->repo->findSetForVariant($variantId);
        $this->assertEquals($variantId, $loadedSet->variantId);

        $assignments = $loadedSet->all();
        $this->assertCount(2, $assignments);

        $primary = $loadedSet->primaryBarcode();
        $this->assertNotNull($primary);
        $this->assertEquals('12345670', $primary->barcode->value);
        $this->assertEquals(BarcodeSymbology::EAN_8, $primary->barcode->symbology);
        $this->assertEquals(BarcodeSource::Internal, $primary->source);
        $this->assertTrue($primary->isPrimary);
    }
}
