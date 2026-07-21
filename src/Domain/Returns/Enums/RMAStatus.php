<?php

namespace InventoryApp\Domain\Returns\Enums;

enum RMAStatus: string
{
    case Requested = 'REQUESTED';
    case Authorized = 'AUTHORIZED';
    case Received = 'RECEIVED';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
}
