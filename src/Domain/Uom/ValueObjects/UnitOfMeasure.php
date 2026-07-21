<?php

namespace InventoryApp\Domain\Uom\ValueObjects;

use InventoryApp\Domain\Uom\Enums\UomCategory;

final class UnitOfMeasure
{
    public function __construct(public readonly string $name, public readonly string $abbreviation, public readonly UomCategory $category)
    {
        if (empty(trim($name)) || empty(trim($abbreviation))) {
            throw new \InvalidArgumentException('UnitOfMeasure name and abbreviation must be non-empty.');
        }
    }

    public function equals(UnitOfMeasure $other): bool
    {
        return $this->name === $other->name && $this->category === $other->category;
    }

    public function isCompatibleWith(UnitOfMeasure $other): bool
    {
        return $this->category === $other->category;
    }

    public function __toString(): string
    {
        return "{$this->name} ({$this->abbreviation})";
    }
}
