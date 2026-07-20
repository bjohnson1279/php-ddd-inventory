<?php

namespace InventoryApp\Application\Inventory\Factories;

use InventoryApp\Application\Inventory\UseCases\ReceiveStock;

interface ReceiveStockFactoryInterface
{
    public function create(): ReceiveStock;
}
