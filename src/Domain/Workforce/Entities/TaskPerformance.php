<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Workforce\Entities;

class TaskPerformance
{
    private string $id;
    private string $operatorId;
    private string $tenantId;
    private string $taskType;
    private string $locationId;
    private float $traversalDistanceMeters;
    private int $durationSeconds;
    private float $accuracyScore;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        string $id,
        string $operatorId,
        string $tenantId,
        string $taskType,
        string $locationId,
        float $traversalDistanceMeters,
        int $durationSeconds,
        float $accuracyScore,
        \DateTimeImmutable $occurredAt
    ) {
        $this->id = $id;
        $this->operatorId = $operatorId;
        $this->tenantId = $tenantId;
        $this->taskType = $taskType;
        $this->locationId = $locationId;
        $this->traversalDistanceMeters = $traversalDistanceMeters;
        $this->durationSeconds = $durationSeconds;
        $this->accuracyScore = $accuracyScore;
        $this->occurredAt = $occurredAt;
    }
}
