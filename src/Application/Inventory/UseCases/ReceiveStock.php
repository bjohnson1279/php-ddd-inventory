<?php

namespace InventoryApp\Application\Inventory\UseCases;

use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\Services\WMSCapacityService;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;
use Ramsey\Uuid\Uuid;
use DateTimeImmutable;

use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;

class ReceiveStock
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
        private readonly ?WMSCapacityService        $capacityService = null,
        private readonly ?CostLayerRepositoryInterface $costLayerRepository = null,
        private readonly ?LedgerRepositoryInterface $ledgerRepository = null
    ) {}

    public function execute(
        SKU $sku,
        LocationId $locationId,
        Quantity $quantity,
        ?string $reference = null,
        ?string $lotNumber = null,
        ?\DateTimeImmutable $expirationDate = null,
        ?int $unitCostCents = null,
        ?string $tenantId = null
    ): void {
        if ($this->capacityService) {
            $this->capacityService->validateCapacity($locationId->getValue(), [
                ['sku' => $sku->getValue(), 'mode' => 'relative', 'quantity' => $quantity->getValue()]
            ]);
        }

        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            throw new Exception("Product not found with SKU: " . $sku->getValue());
        }

        $skipCostLayerCreation = ($unitCostCents !== null && $this->costLayerRepository !== null);

        $product->receiveStockAt($locationId, $quantity, $reference, $skipCostLayerCreation);
        $this->productRepository->save($product);

        if ($skipCostLayerCreation) {
            $resolvedTenantId = $tenantId ?? (function_exists('tenantId') ? tenantId() : 'system');
            $layer = new InventoryCostLayer(
                Uuid::uuid4()->toString(),
                $sku->getValue(),
                $resolvedTenantId,
                $quantity->getValue(),
                $unitCostCents,
                new DateTimeImmutable(),
                $reference
            );
            $layer->lotNumber = $lotNumber;
            $layer->expirationDate = $expirationDate;
            $this->costLayerRepository->save($layer);
        }

        if ($this->ledgerRepository !== null) {
            $resolvedTenantId = $tenantId ?? (function_exists('tenantId') ? tenantId() : 'system');
            $actorId = $_SERVER['auth.user_id'] ?? 'system';
            $ledgerEntry = new LedgerEntry(
                id: Uuid::uuid4()->toString(),
                variantId: $sku->getValue(),
                quantity: $quantity->getValue(),
                reason: ReasonCode::PurchaseReceipt,
                actorId: $actorId,
                referenceId: $reference,
                occurredAt: new DateTimeImmutable(),
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
    }
}
