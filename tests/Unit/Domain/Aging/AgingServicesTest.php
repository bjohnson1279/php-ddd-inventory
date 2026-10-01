<?php

namespace Tests\Unit\Domain\Aging;

use PHPUnit\Framework\TestCase;
use App\Domain\Aging\InventoryAgingService;
use App\Domain\Aging\DeadStockRecommendationEngine;
use App\Domain\Aging\EsgEmissionsCalculator;
use App\Domain\Aging\AgingBucket;
use App\Domain\Aging\RecommendedAction;

class AgingServicesTest extends TestCase
{
    public function testInventoryAgingServiceCalculatesCorrectBucket()
    {
        $service = new InventoryAgingService();
        $now = new \DateTimeImmutable();
        
        $entries = [
            ['quantity' => 10, 'occurred_at' => $now->modify('-200 days')],
            ['quantity' => 5, 'occurred_at' => $now->modify('-100 days')]
        ];
        
        $result = $service->calculateAgingBuckets('SKU1', 'LOC1', 'T1', $now, $entries);
        $this->assertEquals(100, $result['daysSinceLastMovement']);
        $this->assertEquals(AgingBucket::DAYS_91_180, $result['bucket']);
    }

    public function testDeadStockRecommendationEngine()
    {
        $engine = new DeadStockRecommendationEngine();
        
        // 200 days old -> Liquidate
        $analysis1 = $engine->analyze('SKU1', 'L1', 'T1', 50, 100, 200, AgingBucket::OVER_180_DAYS, false);
        $this->assertTrue($analysis1->isDeadStock);
        $this->assertEquals(RecommendedAction::LIQUIDATE, $analysis1->recommendedAction);
        $this->assertEquals(5000, $analysis1->lockedCapitalCents);
        
        // 200 days old but few items -> Donate
        $analysis2 = $engine->analyze('SKU1', 'L1', 'T1', 5, 100, 200, AgingBucket::OVER_180_DAYS, false);
        $this->assertEquals(RecommendedAction::DONATE, $analysis2->recommendedAction);

        // 100 days old + overstock -> Markdown
        $analysis3 = $engine->analyze('SKU1', 'L1', 'T1', 50, 100, 100, AgingBucket::DAYS_91_180, true);
        $this->assertFalse($analysis3->isDeadStock);
        $this->assertEquals(RecommendedAction::MARKDOWN, $analysis3->recommendedAction);
    }

    public function testEsgEmissionsCalculator()
    {
        $calc = new EsgEmissionsCalculator();
        $this->assertEquals(25.0, $calc->calculateScrapEmissions('SKU1', 10, 2.5));
    }
}
