<?php

namespace App\Domain\Labor;

class ScheduleStatus {
    public const DRAFT = 'DRAFT';
    public const PUBLISHED = 'PUBLISHED';
}

class OperatorProfile {
    public string $operatorId;
    public string $tenantId;
    public float $targetPicksPerHour;
    public int $maxConsecutiveHours;
    public array $certifications;

    public function __construct(
        string $operatorId,
        string $tenantId,
        float $targetPicksPerHour,
        int $maxConsecutiveHours,
        array $certifications = []
    ) {
        $this->operatorId = $operatorId;
        $this->tenantId = $tenantId;
        $this->targetPicksPerHour = $targetPicksPerHour;
        $this->maxConsecutiveHours = $maxConsecutiveHours;
        $this->certifications = $certifications;
    }
}

class OperatorPerformanceKpi {
    public string $operatorId;
    public \DateTimeImmutable $targetDate;
    public float $actualPicksPerHour;
    public float $cycleCountAccuracyPercent;
    public float $traversalDistanceMeters;

    public function __construct(
        string $operatorId,
        \DateTimeImmutable $targetDate,
        float $actualPicksPerHour,
        float $cycleCountAccuracyPercent,
        float $traversalDistanceMeters
    ) {
        $this->operatorId = $operatorId;
        $this->targetDate = $targetDate;
        $this->actualPicksPerHour = $actualPicksPerHour;
        $this->cycleCountAccuracyPercent = $cycleCountAccuracyPercent;
        $this->traversalDistanceMeters = $traversalDistanceMeters;
    }
}

class PredictiveStaffingSchedule {
    public string $scheduleId;
    public \DateTimeImmutable $targetDate;
    public int $projectedInboundVolume;
    public int $projectedOutboundVolume;
    public int $recommendedHeadcount;
    public string $status;

    public function __construct(
        string $scheduleId,
        \DateTimeImmutable $targetDate,
        int $projectedInboundVolume,
        int $projectedOutboundVolume,
        int $recommendedHeadcount,
        string $status = ScheduleStatus::DRAFT
    ) {
        $this->scheduleId = $scheduleId;
        $this->targetDate = $targetDate;
        $this->projectedInboundVolume = $projectedInboundVolume;
        $this->projectedOutboundVolume = $projectedOutboundVolume;
        $this->recommendedHeadcount = $recommendedHeadcount;
        $this->status = $status;
    }

    public function publish(): void {
        if ($this->status !== ScheduleStatus::DRAFT) {
            throw new \Exception("Can only publish DRAFT schedules");
        }
        $this->status = ScheduleStatus::PUBLISHED;
    }
}
