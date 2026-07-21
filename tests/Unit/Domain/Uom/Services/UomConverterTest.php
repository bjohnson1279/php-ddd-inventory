<?php

namespace Tests\Unit\Domain\Uom\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure;
use InventoryApp\Domain\Uom\ValueObjects\Quantity;
use InventoryApp\Domain\Uom\Enums\UomCategory;
use InventoryApp\Domain\Uom\Aggregates\ProductUomConfiguration;
use InventoryApp\Domain\Uom\Services\UomConverter;

class UomConverterTest extends TestCase
{
    public function testConvertBoxesToPieces()
    {
        $base = new UnitOfMeasure('Piece', 'pc', UomCategory::Discrete);
        $box = new UnitOfMeasure('Box', 'bx', UomCategory::Discrete);

        $config = new ProductUomConfiguration('cfg-1', 'variant-1', $base);
        $config->addConversionRule($box, 12.0);

        $converter = new UomConverter();

        $from = new Quantity(2, $box); // 2 boxes
        $to = $converter->convert($from, $base, $config);

        $this->assertEquals(24, $to->amount);

        // cost conversion: 1200 cents per box -> 100 cents per piece
        $costPerBox = 1200;
        $costPerPiece = $converter->convertCost($costPerBox, $box, $base, $config);
        $this->assertEquals(100, $costPerPiece);
    }

    public function testIncompatibleUnitsThrows()
    {
        $this->expectException(\DomainException::class);
        $base = new UnitOfMeasure('Kilogram', 'kg', UomCategory::Weight);
        $piece = new UnitOfMeasure('Piece', 'pc', UomCategory::Discrete);
        $config = new ProductUomConfiguration('cfg-2', 'variant-2', $base);
        $converter = new UomConverter();
        $converter->convert(new Quantity(1, $piece), $base, $config);
    }
}
