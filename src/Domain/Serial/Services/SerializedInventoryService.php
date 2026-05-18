<?php

namespace InventoryApp\Domain\Serial\Services;

use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;

class SerializedInventoryService
{
    public function __construct(private readonly $serialRepo, private readonly LedgerRepositoryInterface $ledger, private readonly \Psr\EventDispatcher\EventDispatcherInterface $events) {}

    public function register(SerialNumber $serialNumber, string $variantId, string $tenantId, string $locationId, string $actorId): SerializedItem
    {
        if ($this->serialRepo->isRegistered($serialNumber, $tenantId)) {
            throw new \DomainException('Serial already registered');
        }

        $item = new SerializedItem(bin2hex(random_bytes(8)), $variantId, $serialNumber, $tenantId, $locationId);
        $this->serialRepo->save($item);
        return $item;
    }

    public function receive(SerialNumber $serialNumber, string $tenantId, string $location, string $purchaseOrderId, int $unitCostCents, string $actorId): void
    {
        $item = $this->serialRepo->findBySerialOrFail($serialNumber, $tenantId);
        $item->receive($location, $actorId, $purchaseOrderId);

        $this->ledger->append(new LedgerEntry(bin2hex(random_bytes(8)), $item->variantId, 1, ReasonCode::PurchaseReceipt, $actorId, $purchaseOrderId, new \DateTimeImmutable(), ['serialNumber' => $serialNumber->value, 'unitCostCents' => $unitCostCents]));

        $this->serialRepo->save($item);
        foreach ($item->releaseEvents() as $e) $this->events->dispatch($e);
    }

    public function sell(SerialNumber $serialNumber, string $tenantId, string $saleId, string $actorId): void
    {
        $item = $this->serialRepo->findBySerialOrFail($serialNumber, $tenantId);
        $item->sell($saleId, $actorId);
        $this->ledger->append(new LedgerEntry(bin2hex(random_bytes(8)), $item->variantId, -1, ReasonCode::Sale, $actorId, $saleId, new \DateTimeImmutable(), ['serialNumber' => $serialNumber->value]));
        $this->serialRepo->save($item);
        foreach ($item->releaseEvents() as $e) $this->events->dispatch($e);
    }

    public function isConsistentWithLedger(string $variantId): bool
    {
        $ledgerQty = $this->ledger->currentQuantity($variantId);
        $inStockCount = $this->serialRepo->countByStatus($variantId, \InventoryApp\Domain\Serial\Enums\SerializedItemStatus::InStock);
        return $ledgerQty === $inStockCount;
    }
}
