<?php

namespace InventoryApp\Domain\Accounting\Enums;

enum AccountingMethod: string
{
    case Cash = 'cash';
    case Accrual = 'accrual';
}
