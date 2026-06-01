<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

use Exception;

class ConcurrencyException extends Exception
{
    public function __construct(string $message = "A concurrency error occurred during persistence.")
    {
        parent::__construct($message);
    }
}
