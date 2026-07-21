<?php

namespace InventoryApp\Domain\Barcode\Services;

use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;

class InternalBarcodeGenerator
{
    private const PREFIX = 'INV';

    public function __construct(
        private readonly BarcodeRegistry $registry,
    ) {}

    public function generate(string $variantId, string $tenantId): Barcode
    {
        $attempts = 0;

        do {
            $value = $this->buildValue($variantId, $tenantId, $attempts);
            $attempts++;

            if ($attempts > 5) {
                throw new \RuntimeException('Could not generate a unique barcode after 5 attempts.');
            }
        } while ($this->registry->isRegistered($value));

        return new Barcode(BarcodeSymbology::CODE_128, $value);
    }

    private function buildValue(string $variantId, string $tenantId, int $salt): string
    {
        $tenantFragment  = strtoupper(substr(md5($tenantId), 0, 4));
        $variantFragment = strtoupper(substr(md5($variantId . $salt), 0, 8));
        $tenantFragment  = strtoupper(substr(hash('sha256', $tenantId), 0, 4));
        $variantFragment = strtoupper(substr(hash('sha256', $variantId . $salt), 0, 8));

        return self::PREFIX . '-' . $tenantFragment . '-' . $variantFragment;
        // e.g. INV-A3F2-0C8E4B1D
    }
}
