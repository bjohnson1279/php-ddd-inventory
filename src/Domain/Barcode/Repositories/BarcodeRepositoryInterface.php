<?php

namespace InventoryApp\Domain\Barcode\Repositories;

use InventoryApp\Domain\Barcode\Aggregates\VariantBarcodeSet;
use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSource;

interface BarcodeRepositoryInterface
{
    public function registerAssignment(string $variantId, Barcode $barcode, BarcodeSource $source, bool $isPrimary = false): void;

    public function findVariantByBarcodeValue(string $value): ?string;

    public function findSetForVariant(string $variantId): VariantBarcodeSet;

    public function saveSet(VariantBarcodeSet $set): void;
}
