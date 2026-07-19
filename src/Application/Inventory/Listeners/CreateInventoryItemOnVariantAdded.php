<?php

namespace InventoryApp\Application\Inventory\Listeners;

use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Application\Inventory\UseCases\RegisterProduct;
use Ramsey\Uuid\Uuid;

class CreateInventoryItemOnVariantAdded
{
    private ?RegisterProduct $registerProductUseCase;

    public function __construct(?RegisterProduct $registerProductUseCase = null)
    {
        $this->registerProductUseCase = $registerProductUseCase;
    }

    public function handle(VariantAddedToCatalog $event): void
    {
        $useCase = $this->registerProductUseCase;
        if ($useCase === null) {
            $tenantId = function_exists('tenantId') ? tenantId() : 'system';
            $productRepo = \InventoryApp\Infrastructure\ServiceContainer::productRepo($tenantId);
            $useCase = new RegisterProduct(
                $productRepo,
                \InventoryApp\Infrastructure\ServiceContainer::dispatcher()
            );
        }

        // When a new variant is cataloged, we automatically register it in the inventory system
        // with 0 stock, assigned to the default STOREFRONT location.
        $useCase->execute(
            Uuid::uuid4()->toString(), // Generate a new UUID for the inventory item
            $event->getSku()->getValue(),
            $event->getProductName() . ' (' . $event->getSku()->getValue() . ')',
            $event->getDepartment()->getValue(),
            'LOC-STOREFRONT',
            0 // Initial quantity
        );
    }
}
