<?php

namespace App\Domain\CycleCount;

class CycleCount
{
    private string $id;
    private string $tenantId;
    private string $name;
    private string $status;
    private ?string $abcClass;
    private ?string $zone;
    private bool $isBlindCount;
    private ?string $assignedTo;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $tenantId,
        string $name,
        string $status,
        ?string $abcClass,
        ?string $zone,
        bool $isBlindCount,
        ?string $assignedTo,
        \DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->name = $name;
        $this->status = $status;
        $this->abcClass = $abcClass;
        $this->zone = $zone;
        $this->isBlindCount = $isBlindCount;
        $this->assignedTo = $assignedTo;
        $this->createdAt = $createdAt;
    }

    public function getId(): string { return $this->id; }
}
