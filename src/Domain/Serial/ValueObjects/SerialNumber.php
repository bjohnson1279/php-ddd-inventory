<?php

namespace InventoryApp\Domain\Serial\ValueObjects;

final class SerialNumber
{
    public readonly string $value;

    public function __construct(string $raw)
    {
        $normalized = strtoupper(trim($raw));
        if (empty($normalized)) throw new \InvalidArgumentException('Serial number cannot be empty.');
        if (strlen($normalized) > 100) throw new \InvalidArgumentException('Serial number cannot exceed 100 characters.');
        if (!preg_match('/^[A-Z0-9\-\.\/]+$/', $normalized)) throw new \InvalidArgumentException('Serial number contains invalid characters.');
        $this->value = $normalized;
    }

    public function equals(SerialNumber $other): bool { return $this->value === $other->value; }
}
