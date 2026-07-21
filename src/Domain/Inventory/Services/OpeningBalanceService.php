<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;

class OpeningBalanceService
{
    public function __construct(private readonly LedgerRepositoryInterface $ledger, private readonly \Psr\EventDispatcher\EventDispatcherInterface $events) {}

    public function process(StockOnboarding $onboarding, string $actorId): void
    {
        if (!$onboarding->isSubmitted()) throw new \DomainException('Only submitted onboardings can be processed.');

        foreach ($onboarding->items() as $item) {
            if ($this->ledger->hasAnyEntries($item->variantId, $onboarding->locationId)) {
                throw new \DomainException('Opening balance conflict for variant '.$item->variantId);
            }
        }

        $entries = [];
        $eventsToDispatch = [];
        foreach ($onboarding->items() as $item) {
            $entry = new LedgerEntry(\Ramsey\Uuid\Uuid::uuid4()->toString(), $item->variantId, $item->quantity, ReasonCode::OpeningBalance, $actorId, $onboarding->id, $onboarding->asOfDate, ['unitCostCents' => $item->unitCostCents, 'locationId' => $onboarding->locationId]);
            $entries[] = $entry;
            $eventsToDispatch[] = new \InventoryApp\Domain\Inventory\Events\OpeningBalancePosted(
                $onboarding->id,
                $item->variantId,
                $item->quantity,
                $item->unitCostCents,
                $onboarding->locationId,
                $onboarding->asOfDate,
                new \DateTimeImmutable()
            );
        }
        $this->ledger->appendAll($entries);
        foreach ($eventsToDispatch as $event) {
            $this->events->dispatch($event);
        }
    }
}
