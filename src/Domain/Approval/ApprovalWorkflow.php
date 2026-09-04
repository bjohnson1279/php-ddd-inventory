<?php

namespace InventoryApp\Domain\Approval;

use DomainException;
use DateTimeInterface;
use DateTimeImmutable;

class ApprovalWorkflow
{
    private string $id;
    private string $tenantId;
    private string $name;
    private string $triggerEvent;
    private bool $isActive;
    private array $config; // Contains 'thresholds' and 'steps'
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;

    public function __construct(
        string $id,
        string $tenantId,
        string $name,
        string $triggerEvent,
        bool $isActive,
        array $config,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ) {
        if (trim($triggerEvent) === '') {
            throw new DomainException('Approval workflow trigger event cannot be empty.');
        }
        if (empty($config['steps'])) {
            throw new DomainException('Approval workflow must define at least one approval step.');
        }

        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->name = $name;
        $this->triggerEvent = $triggerEvent;
        $this->isActive = $isActive;
        $this->config = $config;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function shouldTrigger(array $payload): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $thresholds = $this->config['thresholds'] ?? [];
        if (empty($thresholds)) {
            return true; // No thresholds = always trigger
        }

        foreach ($thresholds as $threshold) {
            $field = $threshold['field'] ?? null;
            $operator = $threshold['operator'] ?? null;
            $thresholdValue = $threshold['value'] ?? null;

            if (!isset($payload[$field])) {
                return false;
            }
            $value = $payload[$field];

            switch ($operator) {
                case '>=':
                    if (!($value >= $thresholdValue)) return false;
                    break;
                case '>':
                    if (!($value > $thresholdValue)) return false;
                    break;
                case '<=':
                    if (!($value <= $thresholdValue)) return false;
                    break;
                case '<':
                    if (!($value < $thresholdValue)) return false;
                    break;
                case '==':
                    if (!($value === $thresholdValue)) return false;
                    break;
                case '!=':
                    if (!($value !== $thresholdValue)) return false;
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    public function getStep(int $index): ?array
    {
        return $this->config['steps'][$index] ?? null;
    }

    public function getTotalSteps(): int
    {
        return count($this->config['steps']);
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getName(): string { return $this->name; }
    public function getTriggerEvent(): string { return $this->triggerEvent; }
    public function isActive(): bool { return $this->isActive; }
    public function getConfig(): array { return $this->config; }
    public function getCreatedAt(): DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeInterface { return $this->updatedAt; }
}
