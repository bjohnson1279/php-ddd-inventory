<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Services\WMSCapacityService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class ReceiveStock
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
        private readonly ?WMSCapacityService        $capacityService = null
    ) {}

    public function execute(SKU $sku, LocationId $locationId, Quantity $quantity, ?string $reference = null): void
    {
        if ($this->capacityService) {
            $this->capacityService->validateCapacity($locationId->getValue(), [
                ['sku' => $sku->getValue(), 'mode' => 'relative', 'quantity' => $quantity->getValue()]
            ]);
        }

        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $sku->getValue());
        }

        $product->receiveStockAt($locationId, $quantity, $reference);
        $this->productRepository->save($product);

        foreach ($product->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
