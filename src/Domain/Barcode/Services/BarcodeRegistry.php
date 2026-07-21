<?php

namespace InventoryApp\Domain\Barcode\Services;

class BarcodeRegistry
{
    private array $map = []; // value => variantId

    public function __construct() {}

    public function register(string $value, string $variantId): void { $this->map[strtoupper(trim($value))] = $variantId; }

    public function resolve(string $scannedValue): string
    {
        $v = strtoupper(trim($scannedValue));
        $variantId = $this->map[$v] ?? null;
        if ($variantId === null) throw new \DomainException("No variant found for barcode: {$scannedValue}");
        return $variantId;
    }

    public function isRegistered(string $value): bool { return isset($this->map[strtoupper(trim($value))]); }
}
