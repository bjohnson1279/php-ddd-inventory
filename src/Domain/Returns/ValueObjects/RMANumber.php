<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Returns\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Value Object representing a Return Merchandise Authorization (RMA) Number.
 */
final class RMANumber
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException("RMA number cannot be empty.");
        }

        if (strlen($trimmed) > 64) {
            throw new InvalidArgumentException("RMA number cannot exceed 64 characters.");
        }

        $this->value = $trimmed;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
