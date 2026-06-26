<?php

namespace InventoryApp\Domain\Shipping\Enums;

enum ShipmentStatus: string
{
    case LabelGenerated = 'label_generated';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
