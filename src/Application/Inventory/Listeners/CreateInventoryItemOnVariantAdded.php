<?php

namespace InventoryApp\Application\Inventory\Listeners;

use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Application\Inventory\UseCases\RegisterProduct;

class CreateInventoryItemOnVariantAdded
{
    private RegisterProduct $registerProductUseCase;

    public function __construct(RegisterProduct $registerProductUseCase)
    {
        $this->registerProductUseCase = $registerProductUseCase;
    }

    public function handle(VariantAddedToCatalog $event): void
    {
        // When a new variant is cataloged, we automatically register it in the inventory system
        // with 0 stock, assigned to the default STOREFRONT location.
        $this->registerProductUseCase->execute(
            uniqid('inv_'), // Generate a new ID for the inventory item
            $event->getSku()->getValue(),
            $event->getProductName() . ' (' . $event->getSku()->getValue() . ')',
            $event->getDepartment()->getValue(),
            'LOC-STOREFRONT',
            0 // Initial quantity
        );
    }
}
