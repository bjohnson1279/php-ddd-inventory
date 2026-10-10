<?php

namespace Tests\Unit\Domain\YieldManagement;

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 4) . "/src/Domain/YieldManagement/YieldEntities.php";
require_once dirname(__DIR__, 4) . "/src/Domain/YieldManagement/YieldServices.php";
use App\Domain\YieldManagement\LiquidationProfile;
use App\Domain\YieldManagement\InventoryYieldMetrics;
use App\Domain\YieldManagement\YieldCalculationService;
use App\Domain\YieldManagement\DynamicPricingEngine;
use App\Domain\YieldManagement\MarkdownStatus;

class YieldServicesTest extends TestCase
{
    public function testYieldCalculationService()
    {
        $calc = new YieldCalculationService();
        $profile = new LiquidationProfile("SKU1", 10000, 10, 5000);
        
        $metrics1 = new InventoryYieldMetrics("SKU1", 100, 100, 5.0, 500);
        $this->assertEquals(9000, $calc->calculateOptimalPrice($metrics1, $profile));
        
        $metrics2 = new InventoryYieldMetrics("SKU1", 100, 10, 2.0, 500);
        $this->assertEquals(5000, $calc->calculateOptimalPrice($metrics2, $profile));
    }

    public function testDynamicPricingEngine()
    {
        $calc = new YieldCalculationService();
        $engine = new DynamicPricingEngine($calc);
        
        $profile = new LiquidationProfile("SKU1", 10000, 10, 5000);
        $metrics = new InventoryYieldMetrics("SKU1", 100, 10, 2.0, 500);
        
        $rec = $engine->generateMarkdown($metrics, $profile);
        $this->assertNotNull($rec);
        $this->assertEquals(5000, $rec->recommendedPriceCents);
        $this->assertEquals(MarkdownStatus::PROPOSED, $rec->status);
        
        $rec->approve();
        $this->assertEquals(MarkdownStatus::APPROVED, $rec->status);
        
        $rec->pushToChannels();
        $this->assertEquals(MarkdownStatus::PUSHED_TO_CHANNELS, $rec->status);
    }
}
