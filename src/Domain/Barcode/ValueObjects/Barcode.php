<?php

namespace InventoryApp\Domain\Barcode\ValueObjects;

use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;

final class Barcode
{
    public readonly string $value;

    public function __construct(public readonly BarcodeSymbology $symbology, string $rawValue)
    {
        $this->value = strtoupper(trim($rawValue));
        $this->validate();
    }

    public function equals(Barcode $other): bool { return $this->value === $other->value; }
    public function __toString(): string { return $this->value; }

    private function validate(): void
    {
        match($this->symbology) {
            BarcodeSymbology::UPC_A => $this->validateFixedDigits(12),
            BarcodeSymbology::EAN_13 => $this->validateFixedDigits(13),
            BarcodeSymbology::UPC_E, BarcodeSymbology::EAN_8 => $this->validateFixedDigits(8),
            default => null,
        };
    }

    private function validateFixedDigits(int $len): void
    {
        if (!preg_match('/^\d{' . $len . '}$/', $this->value)) {
            throw new \InvalidArgumentException("Barcode must be exactly {$len} digits: {$this->value}");
        }
    }
}
