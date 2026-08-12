<?php

namespace InventoryApp\Application\Inventory\UseCases\Traits;

use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Exception;

trait ValidatesKit
{
    /**
     * Resolves the kit and kit product details.
     *
     * @param string $kitSkuStr
     * @param KitRepositoryInterface $kitRepository
     * @param ProductRepositoryInterface $productRepository
     * @return array{kit: \InventoryApp\Domain\Kit\Aggregates\Kit, product: \InventoryApp\Domain\Inventory\Entities\Product}
     * @throws Exception
     */
    protected function resolveKitDetails(
        string $kitSkuStr,
        KitRepositoryInterface $kitRepository,
        ProductRepositoryInterface $productRepository
    ): array {
        $kit = $kitRepository->findBySku($kitSkuStr);
        if (!$kit) {
            throw new Exception("Kit with SKU {$kitSkuStr} not found.");
        }

        $kitProduct = $productRepository->findBySku(new SKU($kitSkuStr));
        if (!$kitProduct) {
            throw new Exception("Product variant for Kit SKU {$kitSkuStr} not found.");
        }

        return [
            'kit' => $kit,
            'product' => $kitProduct
        ];
    }
}
