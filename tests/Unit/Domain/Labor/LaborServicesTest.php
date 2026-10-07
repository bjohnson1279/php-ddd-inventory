<?php

namespace Tests\Unit\Domain\Labor;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../src/Domain/Labor/LaborEntities.php';
require_once __DIR__ . '/../../../../src/Domain/Labor/LaborServices.php';
use App\Domain\Labor\ScheduleStatus;
use App\Domain\Labor\OperatorPerformanceService;
use App\Domain\Labor\PredictiveSchedulingEngine;

class LaborServicesTest extends TestCase
{
    public function testOperatorPerformanceService()
    {
        $service = new OperatorPerformanceService();
        
        $kpi = $service->calculateDailyKpi(
            "OP1", new \DateTimeImmutable(), 800, 8.0, 48, 50, 15000.0
        );
        
        $this->assertEquals(100.0, $kpi->actualPicksPerHour);
        $this->assertEquals(96.0, $kpi->cycleCountAccuracyPercent);
    }

    public function testPredictiveSchedulingEngine()
    {
        $engine = new PredictiveSchedulingEngine();
        
        $schedule = $engine->generateStaffingRecommendation(
            new \DateTimeImmutable(), 2000, 6000, 100.0, 8.0
        );
        
        $this->assertEquals(10, $schedule->recommendedHeadcount);
        $this->assertEquals(ScheduleStatus::DRAFT, $schedule->status);
        
        $schedule->publish();
        $this->assertEquals(ScheduleStatus::PUBLISHED, $schedule->status);
    }
}
