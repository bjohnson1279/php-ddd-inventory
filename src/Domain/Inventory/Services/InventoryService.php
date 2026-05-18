<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;

class InventoryService
{
    public function __construct(
        private readonly LedgerRepositoryInterface $ledger,
        private readonly \Psr\EventDispatcher\EventDispatcherInterface $events,
    ) {}

    public function decrementForSale(string $variantId, int $quantity, string $saleId, string $actorId): void
    {
        $this->assertSufficientStock($variantId, $quantity);

        $entry = new LedgerEntry(
            id: bin2hex(random_bytes(16)),
            variantId: $variantId,
            quantity: -$quantity,
            reason: ReasonCode::Sale,
            actorId: $actorId,
            referenceId: $saleId,
            occurredAt: new \DateTimeImmutable(),
            metadata: [],
        );

        $this->ledger->append($entry);
        $this->events->dispatch(new \stdClass()); // lightweight placeholder event
    }

    public function decrementForKitSale(object $kit, int $kitQuantity, string $saleId, string $actorId): void
    {
        if (method_exists($kit, 'components') === false) {
            throw new \InvalidArgumentException('Kit must expose components()');
        }

        $components = $kit->components();
        if (empty($components)) {
            throw new \DomainException('Cannot sell a kit with no components.');
        }

        // Pass 1: validate
        foreach ($components as $component) {
            $needed = ($component->quantity ?? $component['quantity'] ?? 0) * $kitQuantity;
            $variantId = $component->variantId ?? $component['variantId'] ?? null;
            if ($variantId === null) throw new \InvalidArgumentException('Component missing variantId');
            $this->assertSufficientStock($variantId, $needed);
        }

        // Pass 2: append entries
        foreach ($components as $component) {
            $compQty = ($component->quantity ?? $component['quantity'] ?? 0) * $kitQuantity;
            $variantId = $component->variantId ?? $component['variantId'];

            $entry = new LedgerEntry(
                id: bin2hex(random_bytes(16)),
                variantId: $variantId,
                quantity: -$compQty,
                reason: ReasonCode::KitSale,
                actorId: $actorId,
                referenceId: $saleId,
                occurredAt: new \DateTimeImmutable(),
            );

            $this->ledger->append($entry);
            $this->events->dispatch(new \stdClass());
        }
    }

    private function assertSufficientStock(string $variantId, int $needed): void
    {
        $available = $this->ledger->currentQuantity($variantId);
        if ($available < $needed) {
            throw new \DomainException(sprintf('Insufficient inventory for %s: have %d need %d', $variantId, $available, $needed));
        }
    }
}
