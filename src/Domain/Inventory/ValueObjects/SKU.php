<?php

namespace InventoryApp\Domain\Inventory\ValueObjects;

use InventoryApp\Domain\Inventory\Exceptions\InvalidSKUException;

class SKU
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = strtoupper($value);
    }

    private function validate(string $value): void
    {
        if (empty($value) || !preg_match('/^[A-Z0-9-]{3,20}$/i', $value)) {
            throw new InvalidSKUException($value);
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(SKU $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
