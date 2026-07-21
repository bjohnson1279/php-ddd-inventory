<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Procurement\Services\ReorderPolicyService;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class DispatchStock
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
        private readonly ?ReorderPolicyService      $reorderPolicyService = null,
        private readonly ?LedgerRepositoryInterface $ledgerRepository = null,
        private readonly ?CostLayerRepositoryInterface $costLayerRepository = null
    ) {}

    public function execute(
        SKU $sku,
        LocationId $locationId,
        Quantity $quantity,
        ?string $reference = null,
        ?string $lotNumber = null,
        ?string $tenantId = null
    ): void {
        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $sku->getValue());
        }

        $product->dispatchStockAt($locationId, $quantity, $reference);
        $this->productRepository->save($product);

        if ($this->costLayerRepository !== null) {
            $activeLayers = $this->costLayerRepository->getActiveLayers($sku->getValue(), 'expiration_date ASC');
            $qtyToConsume = $quantity->getValue();
            $affectedLayers = [];
            foreach ($activeLayers as $layer) {
                if ($qtyToConsume <= 0) {
                    break;
                }
                if ($lotNumber !== null && $layer->lotNumber !== $lotNumber) {
                    continue;
                }

                $consumed = $layer->consume($qtyToConsume);
                $qtyToConsume -= $consumed;
                $affectedLayers[] = $layer;
            }

            if ($qtyToConsume > 0) {
                throw new Exception("Insufficient cost layers to cover dispatch quantity of " . $quantity->getValue() . ($lotNumber ? " for lot " . $lotNumber : ""));
            }

            $this->costLayerRepository->saveBatch($affectedLayers);
        }

        if ($this->ledgerRepository !== null) {
            $resolvedTenantId = $tenantId ?? (function_exists('tenantId') ? tenantId() : 'system');
            $actorId = $_SERVER['auth.user_id'] ?? 'system';
            $ledgerEntry = new \InventoryApp\Domain\Inventory\Entities\LedgerEntry(
                id: \Ramsey\Uuid\Uuid::uuid4()->toString(),
                variantId: $sku->getValue(),
                quantity: -$quantity->getValue(),
                reason: \InventoryApp\Domain\Inventory\Enums\ReasonCode::Dispatch,
                actorId: $actorId,
                referenceId: $reference,
                occurredAt: new \DateTimeImmutable(),
                metadata: [
                    'lotNumber' => $lotNumber,
                    'locationId' => $locationId->getValue()
                ]
            );
            $this->ledgerRepository->append($ledgerEntry);
        }

        foreach ($product->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }

        if ($this->reorderPolicyService) {
            $currentStock = $product->getStockAt($locationId)->getStockQuantity()->getValue();
            $tenantIdVal = $tenantId ?? (method_exists($this->productRepository, 'getTenantId')
                ? $this->productRepository->getTenantId()
                : 'default-tenant');

            $this->reorderPolicyService->checkPolicy(
                $sku->getValue(),
                $locationId->getValue(),
                $currentStock,
                $tenantIdVal
            );
        }
    }
}
