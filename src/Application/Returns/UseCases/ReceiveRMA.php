<?php

namespace InventoryApp\Application\Returns\UseCases;

use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Services\CostLayerService;
use InventoryApp\Domain\Accounting\Services\AccountingJournalService;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InventoryApp\Domain\Returns\Aggregates\QuarantineItem;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use Exception;
use Ramsey\Uuid\Uuid;

class ReceiveRMA
{
    private readonly CostLayerService $costLayerService;

    public function __construct(
        private readonly RMARepositoryInterface $rmaRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CostLayerRepositoryInterface $costLayerRepository,
        private readonly QuarantineRepositoryInterface $quarantineRepository,
        private readonly AccountingJournalService $journalService,
        private readonly SerializedItemRepositoryInterface $serializedRepository
    ) {
        $this->costLayerService = new CostLayerService($costLayerRepository);
    }

    public function execute(array $dto): void
    {
        $rma = $this->rmaRepository->findById($dto['rmaId']);
        if (!$rma) {
            throw new Exception("RMA with ID {$dto['rmaId']} not found.");
        }

        foreach ($dto['items'] as $item) {
            // Find RMA Item
            $rmaItem = null;
            foreach ($rma->getItems() as $i) {
                if ($i->getVariantId() === $item['variantId']) {
                    $rmaItem = $i;
                    break;
                }
            }
            if (!$rmaItem) {
                throw new Exception("Item with variant ID {$item['variantId']} not found in RMA.");
            }

            $disposition = RMADisposition::from($item['disposition']);

            // Receive item on aggregate root
            $rma->receiveItem($item['variantId'], $item['quantityReceived'], $disposition);

            $targetLocationId = $disposition === RMADisposition::Quarantine
                ? $rma->getLocationId()->getValue() . '-quarantine'
                : $rma->getLocationId()->getValue();

            // Increment stock level on Product aggregate root
            $product = $this->productRepository->findById($item['variantId']);
            if (!$product) {
                throw new Exception("Product not found for variant {$item['variantId']}");
            }
            $product->receiveStockAt(new LocationId($targetLocationId), new Quantity($item['quantityReceived']), "RMA-{$rma->getId()}");
            $this->productRepository->save($product);

            // Create Cost Layer
            $layerId = Uuid::uuid4()->toString();
            $layer = new \InventoryApp\Domain\Accounting\Entities\InventoryCostLayer(
                $layerId,
                $item['variantId'],
                $rma->getTenantId()->getValue(),
                $item['quantityReceived'],
                $rmaItem->getUnitCostCents(),
                new \DateTimeImmutable(),
                "RMA-{$rma->getId()}"
            );
            $this->costLayerRepository->save($layer);

            // Create Quarantine record if quarantined
            if ($disposition === RMADisposition::Quarantine) {
                $qId = Uuid::uuid4()->toString();
                $quarantineItem = new QuarantineItem(
                    $qId,
                    $item['variantId'],
                    $item['quantityReceived'],
                    "Returned from RMA {$rma->getRmaNumber()}",
                    $rma->getLocationId(),
                    $rma->getTenantId()
                );
                $this->quarantineRepository->save($quarantineItem);
            }

            // Post return journal entries
            $totalCostCents = $rmaItem->getUnitCostCents() * $item['quantityReceived'];
            $this->journalService->onStockReturned(
                $rma->getTenantId()->getValue(),
                $item['variantId'],
                $totalCostCents,
                $rma->getId(),
                new \DateTimeImmutable()
            );

            // Handle immediate scrap write-off
            if ($disposition === RMADisposition::Scrap) {
                // Decrement stock level
                $product->dispatchStockAt(new LocationId($targetLocationId), new Quantity($item['quantityReceived']), "RMA-{$rma->getId()}-SCRAP");
                $this->productRepository->save($product);

                // Consume cost layer
                $this->costLayerService->consumeFifoLayers($item['variantId'], $item['quantityReceived']);

                // Post write-off journal entry
                $this->journalService->onInventoryWriteOff(
                    $rma->getTenantId()->getValue(),
                    $rma->getId(),
                    $totalCostCents,
                    new \DateTimeImmutable()
                );
            }

            // Handle Serialized items transitions
            if (!empty($item['serialNumbers'])) {
                foreach ($item['serialNumbers'] as $sn) {
                    $serialItem = $this->serializedRepository->findBySerial(new SerialNumber($sn), $rma->getTenantId()->getValue());
                    if ($serialItem) {
                        $serialItem->acceptReturn($rma->getId(), 'system');

                        if ($disposition === RMADisposition::Restock) {
                            $serialItem->restock('system', $rma->getId());
                        } elseif ($disposition === RMADisposition::Quarantine) {
                            $serialItem->quarantine("Quarantined from RMA {$rma->getRmaNumber()}", 'system', $rma->getId());
                        } elseif ($disposition === RMADisposition::Scrap) {
                            $serialItem->writeOff("Scrapped from RMA {$rma->getRmaNumber()}", 'system', $rma->getId());
                        }
                        $this->serializedRepository->save($serialItem);
                    }
                }
            }
        }

        $this->rmaRepository->save($rma);
    }
}
