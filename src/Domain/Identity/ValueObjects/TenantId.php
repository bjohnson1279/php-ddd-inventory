<?php

namespace InventoryApp\Domain\Identity\ValueObjects;

use InvalidArgumentException;

/**
 * Identifies a specific store/organization in the multi-tenant SaaS system.
 * Every aggregate root that holds tenant-specific data should carry a TenantId.
 */
class TenantId
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException("TenantId cannot be empty");
        }
        if (strlen($value) > 255) {
            throw new InvalidArgumentException("TenantId cannot exceed 255 characters");
        }
        $this->value = trim($value);
    }

    public function getValue(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
    public function __toString(): string { return $this->value; }
}
