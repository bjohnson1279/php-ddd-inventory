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
}
