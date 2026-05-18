<?php

namespace InventoryApp\Domain\Accounting\Enums;

enum DebitCredit: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
