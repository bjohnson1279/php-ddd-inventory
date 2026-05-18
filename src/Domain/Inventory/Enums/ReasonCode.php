<?php

namespace InventoryApp\Domain\Inventory\Enums;

enum ReasonCode: string
{
    case Sale = 'sale';
    case KitSale = 'kit_sale';
    case OpeningBalance = 'opening_balance';
    case PurchaseReceipt = 'purchase_receipt';
}
