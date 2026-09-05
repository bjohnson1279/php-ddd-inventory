<?php

namespace Tests\Unit\Domain\Procurement\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Procurement\Services\DemandVelocityCalculator;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use DateTimeImmutable;

class DemandVelocityCalculatorTest extends TestCase
{
    private LedgerRepositoryInterface $ledgerRepo;
    private ProductRepositoryInterface $productRepo;
    private DemandVelocityCalculator $calculator;

    protected function setUp(): void
    {
        $this->ledgerRepo = $this->createMock(LedgerRepositoryInterface::class);
        $this->productRepo = $this->createMock(ProductRepositoryInterface::class);
        $this->calculator = new DemandVelocityCalculator($this->ledgerRepo, $this->productRepo);
    }

    public function testCalculateDailySalesStatsWithNoSales(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('prod-123');

        $this->ledgerRepo->method('entriesFor')
            ->with('prod-123', 'loc-1')
            ->willReturn([]);

        $stats = $this->calculator->calculateDailySalesStats('SKU-123', 'loc-1', 30, $product);

        $this->assertEquals(0.0, $stats['average']);
        $this->assertEquals(0.0, $stats['stdDev']);
    }

    public function testCalculateDailySalesStatsProductNotFound(): void
    {
        $this->productRepo->method('findBySku')->willReturn(null);

        $stats = $this->calculator->calculateDailySalesStats('SKU-123', 'loc-1', 30);

        $this->assertEquals(0.0, $stats['average']);
        $this->assertEquals(0.0, $stats['stdDev']);
    }

    public function testCalculateDailySalesStatsWithProductLoadedFromRepo(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('prod-123');

        $this->productRepo->method('findBySku')
            ->with($this->callback(function (SKU $sku) {
                return (string) $sku === 'SKU-123';
            }))
            ->willReturn($product);

        $this->ledgerRepo->method('entriesFor')
            ->with('prod-123', 'loc-1')
            ->willReturn([]);

        $stats = $this->calculator->calculateDailySalesStats('SKU-123', 'loc-1', 30);

        $this->assertEquals(0.0, $stats['average']);
        $this->assertEquals(0.0, $stats['stdDev']);
    }

    public function testCalculateDailySalesStatsWithValidSales(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('prod-123');

        $today = new DateTimeImmutable();
        $threeDaysAgo = $today->modify('-3 days');
        $fiveDaysAgo = $today->modify('-5 days');

        $entries = [
            // Valid sales
            new LedgerEntry('entry-1', 'prod-123', -5, ReasonCode::Sale, 'user-1', null, $threeDaysAgo),
            new LedgerEntry('entry-2', 'prod-123', -10, ReasonCode::KitSale, 'user-1', null, $fiveDaysAgo),
            // Ignored because quantity > 0
            new LedgerEntry('entry-3', 'prod-123', 20, ReasonCode::Sale, 'user-1', null, $threeDaysAgo),
            // Ignored because wrong reason
            new LedgerEntry('entry-4', 'prod-123', -5, ReasonCode::WriteOff, 'user-1', null, $fiveDaysAgo),
            // Ignored because outside window (older than 30 days)
            new LedgerEntry('entry-5', 'prod-123', -5, ReasonCode::Sale, 'user-1', null, $today->modify('-31 days')),
        ];

        $this->ledgerRepo->method('entriesFor')
            ->with('prod-123', 'loc-1')
            ->willReturn($entries);

        $stats = $this->calculator->calculateDailySalesStats('SKU-123', 'loc-1', 30, $product);

        $expectedAverage = 15.0 / 30.0;

        // Calculate expected variance:
        // 28 days with 0 sales
        // 1 day with 5 sales
        // 1 day with 10 sales
        $varianceSum = (28 * pow(0 - $expectedAverage, 2))
                     + pow(5 - $expectedAverage, 2)
                     + pow(10 - $expectedAverage, 2);

        $expectedStdDev = sqrt($varianceSum / 30);

        $this->assertEquals($expectedAverage, $stats['average']);
        $this->assertEqualsWithDelta($expectedStdDev, $stats['stdDev'], 0.0001);
    }
}
