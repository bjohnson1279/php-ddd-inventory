<?php

namespace InventoryApp\Domain\Returns\Enums;

enum RMADisposition: string
{
    case Restock = 'RESTOCK';
    case Scrap = 'SCRAP';
    case Quarantine = 'QUARANTINE';
}
