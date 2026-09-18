<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Workforce\Entities;

class LaborShift
{
    private string $id;
    private string $operatorId;
    private string $tenantId;
    private \DateTimeImmutable $startTime;
    private \DateTimeImmutable $endTime;
    private string $status;
    private int $predictedDemand;
    private int $actualCompleted;

    public function __construct(
        string $id,
        string $operatorId,
        string $tenantId,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        string $status,
        int $predictedDemand,
        int $actualCompleted
    ) {
        $this->id = $id;
        $this->operatorId = $operatorId;
        $this->tenantId = $tenantId;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->status = $status;
        $this->predictedDemand = $predictedDemand;
        $this->actualCompleted = $actualCompleted;
    }

    public function getId(): string { return $this->id; }
    public function getOperatorId(): string { return $this->operatorId; }
}
