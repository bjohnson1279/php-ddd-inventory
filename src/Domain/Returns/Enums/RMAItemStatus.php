<?php

namespace InventoryApp\Domain\Returns\Enums;

enum RMAItemStatus: string
{
    case Pending = 'PENDING';
    case Received = 'RECEIVED';
    case Rejected = 'REJECTED';
}
