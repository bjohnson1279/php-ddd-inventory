<?php

namespace InventoryApp\Domain\Procurement\Enums;

enum PurchaseOrderStatus: string
{
    case Draft             = 'DRAFT';
    case Approved          = 'APPROVED';
    case Sent              = 'SENT';
    case PartiallyReceived = 'PARTIALLY_RECEIVED';
    case Received          = 'RECEIVED';
    case Closed            = 'CLOSED';
}
