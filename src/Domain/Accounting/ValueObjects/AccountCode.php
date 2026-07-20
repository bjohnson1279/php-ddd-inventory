<?php

namespace InventoryApp\Domain\Accounting\ValueObjects;

final class AccountCode
{
    public function __construct(public readonly string $code, public readonly string $name, public readonly string $category) {}

    public static function cash(): self { return new self('1000', 'Cash', 'asset'); }
    public static function accountsReceivable(): self { return new self('1100', 'Accounts Receivable', 'asset'); }
    public static function inventory(): self { return new self('1200', 'Inventory', 'asset'); }
    public static function accountsPayable(): self { return new self('2000', 'Accounts Payable', 'liability'); }
    public static function salesRevenue(): self { return new self('4000', 'Sales Revenue', 'revenue'); }
    public static function costOfGoodsSold(): self { return new self('5000', 'Cost of Goods Sold', 'expense'); }
    public static function inventoryExpense(): self { return new self('5100', 'Inventory Purchases', 'expense'); }
    public static function inventoryWriteOffExpense(): self { return new self('5300', 'Inventory Write-Off Expense', 'expense'); }

    public static function fromCode(string $code): self
    {
        return match ($code) {
            '1000' => self::cash(),
            '1100' => self::accountsReceivable(),
            '1200' => self::inventory(),
            '2000' => self::accountsPayable(),
            '4000' => self::salesRevenue(),
            '5000' => self::costOfGoodsSold(),
            '5100' => self::inventoryExpense(),
            '5300' => self::inventoryWriteOffExpense(),
            default => new self(
                $code,
                'Account ' . $code,
                str_starts_with($code, '2') ? 'liability'
                : (str_starts_with($code, '3') ? 'equity'
                : (str_starts_with($code, '4') ? 'revenue'
                : (str_starts_with($code, '5') ? 'expense' : 'asset')))
            ),
        };
    }
}
