<?php

namespace Tests\Unit\Domain\Accounting;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Accounting\Strategies\FefoCostingStrategy;
use InventoryApp\Domain\Accounting\Strategies\CostingStrategyRegistry;
use InventoryApp\Domain\Accounting\Enums\CostingMethod;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use DateTimeImmutable;

class FefoCostingStrategyTest extends TestCase
{
    public function testRegistryReturnsFefoStrategy(): void
    {
        $strategy = CostingStrategyRegistry::get(CostingMethod::FEFO);
        $this->assertInstanceOf(FefoCostingStrategy::class, $strategy);
    }

    public function testConsumeLayersByEarliestExpirationDate(): void
    {
        $strategy = new FefoCostingStrategy();
        $now = new DateTimeImmutable();
        $expEarlier = $now->modify('+1 day');
        $expLater = $now->modify('+10 days');

        $layer1 = new InventoryCostLayer('l1', 'v1', 't1', 10, 1000, new DateTimeImmutable('2026-01-01'));
        $layer1->expirationDate = $expLater;

        $layer2 = new InventoryCostLayer('l2', 'v1', 't1', 10, 500, new DateTimeImmutable('2026-01-02'));
        $layer2->expirationDate = $expEarlier;

        [$breakdown, $affected] = $strategy->consumeLayers([$layer1, $layer2], 15, 'v1');

        // Layer 2 expires earlier, so its 10 units @ 500 = 5000 cents are consumed first.
        // Then 5 units @ 1000 = 5000 cents from Layer 1 are consumed. Total cost = 10000 cents.
        $this->assertEquals(10000, $breakdown->totalCostCents);
        $this->assertEquals(0, $layer2->remainingQuantity());
        $this->assertEquals(5, $layer1->remainingQuantity());
    }
}
