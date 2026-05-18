<?php

namespace InventoryApp\Domain\Inventory\ValueObjects;

use InvalidArgumentException;

class TransactionType
{
    public const RECEIPT = 'receipt';
    public const SALE = 'sale';
    public const DISPATCH = 'dispatch';
    public const RETURN = 'return';
    public const ADJUSTMENT = 'adjustment';

    private string $value;

    public function __construct(string $value)
    {
        $valid = [self::RECEIPT, self::SALE, self::DISPATCH, self::RETURN, self::ADJUSTMENT];
        if (!in_array($value, $valid, true)) {
            throw new InvalidArgumentException("Invalid transaction type: {$value}");
        }
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
