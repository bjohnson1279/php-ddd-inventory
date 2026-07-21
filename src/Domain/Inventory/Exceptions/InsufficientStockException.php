<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

class InsufficientStockException extends \DomainException
{
    public function __construct(string $sku, int $requested, int $available)
    {
        parent::__construct(sprintf("Insufficient stock for SKU '%s'. Requested: %d, Available: %d.", $sku, $requested, $available));
    }
}
