<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

use Exception;

class InvalidQuantityException extends Exception
{
    public function __construct(int $quantity)
    {
        parent::__construct(sprintf("The quantity '%d' is invalid. Quantity cannot be negative.", $quantity));
    }
}
