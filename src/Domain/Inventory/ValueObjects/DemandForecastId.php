<?php

namespace InventoryApp\Domain\Inventory\ValueObjects;

use InvalidArgumentException;

class DemandForecastId
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException("DemandForecastId cannot be empty.");
        }
        $this->value = trim($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(DemandForecastId $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
