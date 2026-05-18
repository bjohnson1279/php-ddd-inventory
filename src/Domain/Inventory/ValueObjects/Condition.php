<?php

namespace InventoryApp\Domain\Inventory\ValueObjects;

use InvalidArgumentException;

class Condition
{
    public const NEW = 'new';
    public const OPEN_BOX = 'open_box';
    public const DAMAGED = 'damaged';

    private string $value;

    public function __construct(string $value)
    {
        $validConditions = [self::NEW, self::OPEN_BOX, self::DAMAGED];
        if (!in_array($value, $validConditions, true)) {
            throw new InvalidArgumentException("Invalid condition: {$value}");
        }
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Condition $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
