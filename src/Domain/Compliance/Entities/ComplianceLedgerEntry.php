<?php

namespace InventoryApp\Domain\Compliance\Entities;

use DateTime;

class ComplianceLedgerEntry
{
    private string $id;
    private string $tenantId;
    private string $actorId;
    private string $eventType;
    private int $sequenceNumber;
    private string $previousHash;
    private string $currentHash;
    private string $signature;
    private string $payload;
    private DateTime $createdAt;

    public function __construct(
        string $id,
        string $tenantId,
        string $actorId,
        string $eventType,
        int $sequenceNumber,
        string $previousHash,
        string $currentHash,
        string $signature,
        string $payload,
        DateTime $createdAt
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->actorId = $actorId;
        $this->eventType = $eventType;
        $this->sequenceNumber = $sequenceNumber;
        $this->previousHash = $previousHash;
        $this->currentHash = $currentHash;
        $this->signature = $signature;
        $this->payload = $payload;
        $this->createdAt = $createdAt;
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getActorId(): string { return $this->actorId; }
    public function getEventType(): string { return $this->eventType; }
    public function getSequenceNumber(): int { return $this->sequenceNumber; }
    public function getPreviousHash(): string { return $this->previousHash; }
    public function getCurrentHash(): string { return $this->currentHash; }
    public function getSignature(): string { return $this->signature; }
    public function getPayload(): string { return $this->payload; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
}
