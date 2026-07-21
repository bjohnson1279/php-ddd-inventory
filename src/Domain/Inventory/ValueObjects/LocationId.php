<?php

namespace InventoryApp\Domain\Inventory\ValueObjects;

use InvalidArgumentException;

class LocationId
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException("Location ID cannot be empty");
        }

        $trimmed = trim($value);
        if (!str_starts_with($trimmed, 'LOC-') && count(explode('-', $trimmed)) < 6) {
            throw new InvalidArgumentException("Location ID must start with 'LOC-' or follow coordinate format.");
        }

        $this->value = $trimmed;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(LocationId $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
