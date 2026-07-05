<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

class InvalidSKUException extends \DomainException
{
    public function __construct(string $sku)
    {
        parent::__construct(sprintf("The SKU '%s' is invalid. SKUs must be alphanumeric and between 3 and 20 characters.", $sku));
    }
}
