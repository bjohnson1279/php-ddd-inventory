<?php

namespace InventoryApp\Domain\Approval;

use DomainException;
use DateTimeInterface;
use DateTimeImmutable;

class ApprovalRequest
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_ESCALATED = 'ESCALATED';
    public const STATUS_EXPIRED = 'EXPIRED';

    private string $status;
    private int $currentStep;
    /** @var ApprovalDecisionRecord[] */
    private array $decisions;

    private function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $workflowId,
        public readonly string $referenceType,
        public readonly string $referenceId,
        public readonly string $requesterId,
        public readonly array $payload,
        public readonly int $totalSteps,
        string $status = self::STATUS_PENDING,
        int $currentStep = 0,
        array $decisions = [],
        public readonly ?DateTimeInterface $expiresAt = null,
        public readonly ?DateTimeInterface $createdAt = null,
        public readonly ?DateTimeInterface $updatedAt = null
    ) {
        $this->status = $status;
        $this->currentStep = $currentStep;
        $this->decisions = $decisions;
    }

    public static function reconstruct(
        string $id,
        string $tenantId,
        string $workflowId,
        string $referenceType,
        string $referenceId,
        string $requesterId,
        array $payload,
        int $totalSteps,
        string $status,
        int $currentStep,
        array $decisions,
        ?DateTimeInterface $expiresAt = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ): self {
        return new self(
            $id, $tenantId, $workflowId, $referenceType, $referenceId, $requesterId,
            $payload, $totalSteps, $status, $currentStep, $decisions,
            $expiresAt, $createdAt ?? new DateTimeImmutable(), $updatedAt ?? new DateTimeImmutable()
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    public function getDecisions(): array
    {
        return $this->decisions;
    }

    public function approve(ApprovalDecisionRecord $decision, int $requiredCount): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new DomainException("Cannot approve request in status: {$this->status}");
        }
        if ($decision->stepIndex !== $this->currentStep) {
            throw new DomainException("Decision step {$decision->stepIndex} does not match current step {$this->currentStep}.");
        }
        if ($decision->decision !== 'APPROVED') {
            throw new DomainException("Decision must be APPROVED.");
        }

        $this->decisions[] = $decision;

        $currentStepApprovals = 0;
        foreach ($this->decisions as $d) {
            if ($d->stepIndex === $this->currentStep && $d->decision === 'APPROVED') {
                $currentStepApprovals++;
            }
        }

        if ($currentStepApprovals >= $requiredCount) {
            if ($this->currentStep + 1 >= $this->totalSteps) {
                $this->status = self::STATUS_APPROVED;
            } else {
                $this->currentStep++;
            }
        }
    }

    public function reject(ApprovalDecisionRecord $decision): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new DomainException("Cannot reject request in status: {$this->status}");
        }
        if ($decision->decision !== 'REJECTED') {
            throw new DomainException("Rejection decision must have decision = REJECTED.");
        }

        $this->decisions[] = $decision;
        $this->status = self::STATUS_REJECTED;
    }

    public function escalate(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new DomainException("Cannot escalate request in status: {$this->status}");
        }

        if ($this->currentStep + 1 >= $this->totalSteps) {
            $this->status = self::STATUS_EXPIRED;
        } else {
            $this->currentStep++;
            $this->status = self::STATUS_ESCALATED;
        }
    }

    public function expire(): void
    {
        if ($this->status !== self::STATUS_PENDING && $this->status !== self::STATUS_ESCALATED) {
            throw new DomainException("Cannot expire request in status: {$this->status}");
        }
        $this->status = self::STATUS_EXPIRED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_ESCALATED;
    }
}
