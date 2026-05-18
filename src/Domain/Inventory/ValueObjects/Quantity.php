<?php

namespace InventoryApp\Domain\Inventory\ValueObjects;

use InventoryApp\Domain\Inventory\Exceptions\InvalidQuantityException;

class Quantity
{
    private int $value;

    public function __construct(int $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(int $value): void
    {
        if ($value < 0) {
            throw new InvalidQuantityException($value);
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function add(Quantity $quantity): Quantity
    {
        return new self($this->value + $quantity->getValue());
    }

    public function subtract(Quantity $quantity): Quantity
    {
        return new self($this->value - $quantity->getValue());
    }

    public function isGreaterThanOrEqual(Quantity $quantity): bool
    {
        return $this->value >= $quantity->getValue();
    }
}
