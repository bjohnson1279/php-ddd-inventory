<?php

namespace InventoryApp\Domain\Barcode\Enums;

enum BarcodeSource: string
{
    case Supplier = 'supplier';
    case Internal = 'internal';
    case GS1 = 'gs1';
}
