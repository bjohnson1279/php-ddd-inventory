<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Procurement\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable Value Object representing a Purchase Order Number.
 */
final class PurchaseOrderNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException("Purchase order number cannot be empty.");
        }

        if (strlen($trimmed) > 64) {
            throw new InvalidArgumentException("Purchase order number cannot exceed 64 characters.");
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
