<?php

namespace InventoryApp\Domain\Identity\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing an Email Address in the Identity bounded context.
 * Encapsulates email format validation and lowercase normalization.
 */
class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if (empty($trimmed) || !filter_var($trimmed, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE)) {
            throw new InvalidArgumentException("Invalid email address: {$value}");
        }
        $this->value = strtolower($trimmed);
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
