<?php

namespace InventoryApp\Domain\Accounting\Enums;

enum CostingMethod: string
{
    case FIFO = 'fifo';
    case WeightedAverageCost = 'weighted_average_cost';
    case SpecificIdentification = 'specific_identification';
}
