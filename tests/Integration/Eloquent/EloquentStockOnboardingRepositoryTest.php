<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentStockOnboardingRepository;
use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentStockOnboardingRepositoryTest extends TestCase
{
    private EloquentStockOnboardingRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new EloquentStockOnboardingRepository();
    }

    public function test_save_and_retrieve_stock_onboarding(): void
    {
        $id = uuidv4();
        $tenantId = 'test-tenant';
        $locationId = 'LOC-INT';
        $asOfDate = new \DateTimeImmutable('2026-05-29');

        // 1. Create a draft stock onboarding
        $onboarding = new StockOnboarding($id, $tenantId, $locationId, $asOfDate);
        
        $variant1 = uuidv4();
        $variant2 = uuidv4();
        
        $onboarding->setItem($variant1, 10, 1500); // 10 units at $15.00
        $onboarding->setItem($variant2, 5, 2000);  // 5 units at $20.00

        $this->repo->save($onboarding);

        // 2. Retrieve and assert correctness
        $loaded = $this->repo->findOrFail($id);
        $this->assertEquals($id, $loaded->id);
        $this->assertEquals($tenantId, $loaded->tenantId);
        $this->assertEquals($locationId, $loaded->locationId);
        $this->assertEquals('2026-05-29', $loaded->asOfDate->format('Y-m-d'));
        $this->assertFalse($loaded->isSubmitted());

        $items = $loaded->items();
        $this->assertCount(2, $items);

        // Sort items by variant ID to make assertions stable
        usort($items, fn($a, $b) => strcmp($a->variantId, $b->variantId));
        $expectedVariants = [$variant1, $variant2];
        sort($expectedVariants);

        $this->assertEquals($expectedVariants[0], $items[0]->variantId);
        $this->assertEquals($expectedVariants[1], $items[1]->variantId);

        // 3. Submit and resave
        $onboarding->submit();
        $this->repo->save($onboarding);

        $loadedSubmitted = $this->repo->findOrFail($id);
        $this->assertTrue($loadedSubmitted->isSubmitted());
    }

    public function test_find_non_existent_onboarding_throws_exception(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Onboarding not found');
        
        $this->repo->findOrFail(uuidv4());
    }
}
