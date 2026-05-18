<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

use Exception;

class InvalidSKUException extends Exception
{
    public function __construct(string $sku)
    {
        parent::__construct(sprintf("The SKU '%s' is invalid. SKUs must be alphanumeric and between 3 and 20 characters.", $sku));
    }
}
