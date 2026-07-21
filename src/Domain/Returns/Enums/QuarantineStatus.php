<?php

namespace InventoryApp\Domain\Returns\Enums;

enum QuarantineStatus: string
{
    case Quarantined = 'QUARANTINED';
    case Restocked = 'RESTOCKED';
    case Scrapped = 'SCRAPPED';
    case Rtv = 'RTV';
}
