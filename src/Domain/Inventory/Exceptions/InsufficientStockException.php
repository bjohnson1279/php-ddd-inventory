<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $sku, int $requested, int $available)
    {
        parent::__construct(sprintf("Insufficient stock for SKU '%s'. Requested: %d, Available: %d.", $sku, $requested, $available));
    }
}
