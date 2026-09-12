<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Workforce\Entities;

class WarehouseOperator
{
    private string $id;
    private string $userId;
    private string $tenantId;
    private float $averagePicksPerHour;
    private float $totalDistanceWalkedM;
    private float $accuracyScore;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $userId,
        string $tenantId,
        float $averagePicksPerHour,
        float $totalDistanceWalkedM,
        float $accuracyScore,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->tenantId = $tenantId;
        $this->averagePicksPerHour = $averagePicksPerHour;
        $this->totalDistanceWalkedM = $totalDistanceWalkedM;
        $this->accuracyScore = $accuracyScore;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): string { return $this->id; }
    public function getUserId(): string { return $this->userId; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getAveragePicksPerHour(): float { return $this->averagePicksPerHour; }
    public function getTotalDistanceWalkedM(): float { return $this->totalDistanceWalkedM; }
    public function getAccuracyScore(): float { return $this->accuracyScore; }
}
