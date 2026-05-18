<?php

namespace InventoryApp\Application\Catalog\UseCases;

use InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface;
use InventoryApp\Domain\Catalog\Entities\Variant;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Shared\Events\EventDispatcher;
use Exception;

class AddVariant
{
    private CatalogProductRepositoryInterface $repository;
    private EventDispatcher $eventDispatcher;

    public function __construct(
        CatalogProductRepositoryInterface $repository,
        EventDispatcher $eventDispatcher
    ) {
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function execute(
        string $productId, 
        string $variantId, 
        string $skuValue, 
        array $attributes, 
        float $price
    ): void {
        $product = $this->repository->findById($productId);
        
        if (!$product) {
            throw new Exception("Catalog Product not found with ID: " . $productId);
        }

        $sku = new SKU($skuValue);
        
        $variant = new Variant($variantId, $productId, $sku, $attributes, $price);
        $product->addVariant($variant);
        
        $this->repository->save($product);
        
        foreach ($product->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
