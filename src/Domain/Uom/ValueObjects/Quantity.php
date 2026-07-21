<?php

namespace InventoryApp\Domain\Uom\ValueObjects;

use InventoryApp\Domain\Uom\Enums\UomCategory;

final class Quantity
{
    public function __construct(public readonly float $amount, public readonly UnitOfMeasure $unit)
    {
        if ($this->amount < 0) {
            throw new \InvalidArgumentException('Quantity amount cannot be negative.');
        }
    }

    public function add(Quantity $other): self
    {
        $this->assertSameUnit($other);
        return new self($this->amount + $other->amount, $this->unit);
    }

    public function subtract(Quantity $other): self
    {
        $this->assertSameUnit($other);
        $result = $this->amount - $other->amount;
        if ($result < 0) throw new \DomainException('Resulting quantity would be negative.');
        return new self($result, $this->unit);
    }

    public function multiplyBy(int|float $factor): self
    {
        return new self($this->amount * $factor, $this->unit);
    }

    public function toBaseInteger(): int
    {
        if ($this->unit->category !== UomCategory::Discrete) {
            throw new \DomainException('Use toBaseInteger() only for discrete quantities.');
        }
        if (fmod($this->amount, 1.0) !== 0.0) {
            throw new \DomainException("Discrete quantity must be a whole number; got {$this->amount}.");
        }
        return (int) $this->amount;
    }

    public function __toString(): string
    {
        return "{$this->amount} {$this->unit->abbreviation}";
    }

    private function assertSameUnit(Quantity $other): void
    {
        if (!$this->unit->equals($other->unit)) {
            throw new \DomainException('Cannot operate on different units directly.');
        }
    }
}
