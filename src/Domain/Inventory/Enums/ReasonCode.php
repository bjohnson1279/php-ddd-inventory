<?php

namespace InventoryApp\Domain\Inventory\Enums;

enum ReasonCode: string
{
    case Sale            = 'sale';
    case KitSale         = 'kit_sale';
    case OpeningBalance  = 'opening_balance';
    case PurchaseReceipt = 'purchase_receipt';
    case Return          = 'return';
    case Transfer        = 'transfer';
    case Adjustment      = 'adjustment';
    case WriteOff        = 'write_off';
    case Reconciliation  = 'reconciliation';
    case KitAssembly     = 'kit_assembly';
    case KitDisassembly  = 'kit_disassembly';
    case Dispatch        = 'dispatch';
}
