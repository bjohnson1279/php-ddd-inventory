<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

class InvalidQuantityException extends \DomainException
{
    public function __construct(int $quantity)
    {
        parent::__construct(sprintf("The quantity '%d' is invalid. Quantity cannot be negative.", $quantity));
    }
}
