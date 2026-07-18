<?php

namespace InventoryApp\Application\Shipping\UseCases;

use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;

use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Domain\Shipping\Aggregates\Shipment;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Inventory\Exceptions\InsufficientStockException;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Exception;

class PurchaseShippingLabel
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly CarrierServiceInterface $carrierService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LedgerRepositoryInterface $ledgerRepository,
        private readonly JournalRepositoryInterface $journalRepository,
        private readonly OutboxRepositoryInterface $outboxRepository,
        private readonly ?CostLayerRepositoryInterface $costLayerRepository = null
    ) {}

    public function execute(
        string $sku,
        int $quantity,
        string $destinationAddress,
        string $carrier,
        string $locationId,
        string $tenantId
    ): PurchaseShippingLabelResult {
        if (trim($sku) === '' || $quantity <= 0 || trim($destinationAddress) === '' || trim($carrier) === '' || trim($locationId) === '' || trim($tenantId) === '') {
            throw new Exception("Missing required parameters for shipping label purchase.");
        }

        $skuVO = new SKU($sku);
        $locationIdVO = new LocationId($locationId);
        $quantityVO = new Quantity($quantity);

        // 1. Validate stock level
        $product = $this->productRepository->findBySku($skuVO);
        if (!$product) {
            throw new Exception("Inventory item not found for SKU {$sku} at location {$locationId}.");
        }

        $available = $product->getStockAt($locationIdVO)->getStockQuantity()->getValue();
        if ($available < $quantity) {
            throw new InsufficientStockException($sku, $quantity, $available);
        }

        // 2. Generate carrier label
        $labelResult = $this->carrierService->generateLabel($sku, $quantity, $destinationAddress, $carrier);

        // 3. Dispatch stock
        $product->dispatchStockAt($locationIdVO, $quantityVO, "SHIPMENT-" . $labelResult->trackingNumber);
        $this->productRepository->save($product);

        // 4. Log historical ledger dispatch entry
        $actorId = $_SERVER['auth.user_id'] ?? 'system';
        $ledgerEntry = new \InventoryApp\Domain\Inventory\Entities\LedgerEntry(
            id: Uuid::uuid4()->toString(),
            variantId: $sku,
            quantity: -$quantity,
            reason: \InventoryApp\Domain\Inventory\Enums\ReasonCode::Dispatch,
            actorId: $actorId,
            referenceId: "SHIPMENT-" . $labelResult->trackingNumber,
            occurredAt: new DateTimeImmutable(),
            metadata: [
                'locationId' => $locationId
            ]
        );
        $this->ledgerRepository->append($ledgerEntry);

        // Consume cost layers if repository is provided
        if ($this->costLayerRepository !== null) {
            $activeLayers = $this->costLayerRepository->getActiveLayers($sku, 'expiration_date ASC');
            $qtyToConsume = $quantity;
            $affectedLayers = [];
            foreach ($activeLayers as $layer) {
                if ($qtyToConsume <= 0) {
                    break;
                }
                $consumed = $layer->consume($qtyToConsume);
                $qtyToConsume -= $consumed;
                $affectedLayers[] = $layer;
            }
            if ($qtyToConsume > 0) {
                throw new Exception("Insufficient cost layers to cover dispatch quantity of {$quantity} for SKU {$sku}");
            }
            $this->costLayerRepository->saveBatch($affectedLayers);
        }

        // 5. Create Shipment record
        $shipmentId = Uuid::uuid4()->toString();
        $shipment = new Shipment(
            $shipmentId,
            $sku,
            $quantity,
            $destinationAddress,
            $carrier,
            $labelResult->trackingNumber,
            $labelResult->labelUrl,
            $labelResult->rateCents,
            ShipmentStatus::LabelGenerated,
            new DateTimeImmutable()
        );
        $this->shipmentRepository->save($shipment);

        // 6. Generate double-entry ledger listings
        $method = AccountingMethod::Accrual;
        $entryId = Uuid::uuid4()->toString();
        $entry = new JournalEntry(
            $entryId,
            $tenantId,
            new DateTimeImmutable(),
            "Shipping carrier label purchased: {$carrier} {$labelResult->trackingNumber}",
            $shipmentId,
            $method
        );

        $freightExpense = new AccountCode("5400", "Shipping & Freight Expense", "expense");
        $freightLiability = new AccountCode("2100", "Accrued Shipping Liabilities", "liability");

        $entry->addLine($freightExpense, $labelResult->rateCents, DebitCredit::Debit, "Carrier shipping expense");
        $entry->addLine($freightLiability, $labelResult->rateCents, DebitCredit::Credit, "Accrued carrier liabilities");

        $entry->assertBalanced();
        $this->journalRepository->save($entry);

        // 7. Write outbox event
        $this->outboxRepository->save(new \InventoryApp\Domain\Shipping\Events\ShipmentCreatedEvent(
            shipmentId: $shipmentId,
            sku: $sku,
            quantity: $quantity,
            carrier: $carrier,
            trackingNumber: $labelResult->trackingNumber,
            rateCents: $labelResult->rateCents
        ));

        return new PurchaseShippingLabelResult(
            $shipmentId,
            $labelResult->trackingNumber,
            $labelResult->labelUrl,
            $labelResult->rateCents
        );
    }
}
