<?php

namespace InventoryApp\Domain\Inventory\ValueObjects;

use Exception;

class CountStatus
{
    public const STARTED = 'STARTED';
    public const COMPLETED = 'COMPLETED';

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, [self::STARTED, self::COMPLETED])) {
            throw new Exception("Invalid status for Inventory Count.");
        }
        $this->value = $value;
    }

    public static function started(): self
    {
        return new self(self::STARTED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }
    
    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(CountStatus $other): bool
    {
        return $this->value === $other->getValue();
    }
}
