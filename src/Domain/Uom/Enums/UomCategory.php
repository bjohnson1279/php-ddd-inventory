<?php

namespace InventoryApp\Domain\Uom\Enums;

enum UomCategory: string
{
    case Discrete = 'discrete';
    case Weight = 'weight';
    case Volume = 'volume';
}
