<?php

namespace App\Domain\Labor;

class OperatorPerformanceService {
    public function calculateDailyKpi(
        string $operatorId,
        \DateTimeImmutable $targetDate,
        int $totalPicks,
        float $hoursWorked,
        int $accurateCounts,
        int $totalCounts,
        float $distanceMeters
    ): OperatorPerformanceKpi {
        
        $picksPerHour = $hoursWorked > 0 ? $totalPicks / $hoursWorked : 0.0;
        $accuracy = $totalCounts > 0 ? ($accurateCounts / $totalCounts) * 100.0 : 100.0;
        
        return new OperatorPerformanceKpi(
            $operatorId,
            $targetDate,
            $picksPerHour,
            $accuracy,
            $distanceMeters
        );
    }
}

class PredictiveSchedulingEngine {
    public function generateStaffingRecommendation(
        \DateTimeImmutable $targetDate,
        int $projectedInboundVolume,
        int $projectedOutboundVolume,
        float $averageOperatorTargetPicks,
        float $shiftDurationHours = 8.0
    ): PredictiveStaffingSchedule {
        
        $totalVolume = $projectedInboundVolume + $projectedOutboundVolume;
        $picksPerShift = $averageOperatorTargetPicks * $shiftDurationHours;
        
        $recommendedHeadcount = 0;
        if ($picksPerShift > 0) {
            $recommendedHeadcount = (int)ceil($totalVolume / $picksPerShift);
        }
        
        return new PredictiveStaffingSchedule(
            uniqid(),
            $targetDate,
            $projectedInboundVolume,
            $projectedOutboundVolume,
            $recommendedHeadcount
        );
    }
}
