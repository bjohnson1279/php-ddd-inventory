<?php

namespace InventoryApp\Domain\Inventory\Aggregates;

use InventoryApp\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;

enum StockOnboardingStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}

final class StockOnboardingItem
{
    public readonly Money $unitCost;
    public readonly int $unitCostCents;

    public function __construct(
        public readonly string $variantId,
        public readonly int $quantity,
        Money|int $unitCostCents
    ) {
        if ($this->quantity < 0) {
            throw new InvalidArgumentException('Opening balance quantity cannot be negative.');
        }

        if ($unitCostCents instanceof Money) {
            $this->unitCost = $unitCostCents;
            $this->unitCostCents = $unitCostCents->getAmountInCents();
        } else {
            if ($unitCostCents < 0) {
                throw new InvalidArgumentException('Unit cost cannot be negative.');
            }
            $this->unitCost = Money::fromCents($unitCostCents);
            $this->unitCostCents = $unitCostCents;
        }

        if ($this->unitCost->isNegative()) {
            throw new InvalidArgumentException('Unit cost cannot be negative.');
        }
    }

    public function getUnitCost(): Money
    {
        return $this->unitCost;
    }
}

class StockOnboarding
{
    private StockOnboardingStatus $status;
    private array $items = [];
    private array $domainEvents = [];

    public function __construct(public readonly string $id, public readonly string $tenantId, public readonly string $locationId, public readonly \DateTimeImmutable $asOfDate)
    {
        $this->status = StockOnboardingStatus::Draft;
    }

    public function setItem(string $variantId, int $quantity, Money|int $unitCostCents): void
    {
        $this->assertDraft();
        $this->items[$variantId] = new StockOnboardingItem($variantId, $quantity, $unitCostCents);
    }

    public function removeItem(string $variantId): void { $this->assertDraft(); unset($this->items[$variantId]); }

    public function submit(): void
    {
        $this->assertDraft();
        if (empty($this->items)) throw new \DomainException('Cannot submit empty onboarding');
        $this->status = StockOnboardingStatus::Submitted;
        $this->domainEvents[] = new \InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted(
            $this->id,
            $this->tenantId,
            $this->locationId,
            $this->asOfDate,
            new \DateTimeImmutable()
        );
    }

    public function isSubmitted(): bool { return $this->status === StockOnboardingStatus::Submitted; }
    public function items(): array { return array_values($this->items); }
    public function releaseEvents(): array { $e = $this->domainEvents; $this->domainEvents = []; return $e; }

    private function assertDraft(): void
    {
        if ($this->status !== StockOnboardingStatus::Draft) throw new \DomainException('Onboarding already submitted.');
    }
}
