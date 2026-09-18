<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Workforce\Services;

use InventoryApp\Domain\Workforce\Entities\WarehouseOperator;
use InventoryApp\Domain\Workforce\Entities\TaskPerformance;
use InventoryApp\Domain\Workforce\Repositories\WorkforceRepositoryInterface;

class WorkforceService
{
    private WorkforceRepositoryInterface $repository;

    public function __construct(WorkforceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getOrCreateOperator(string $userId, string $tenantId): WarehouseOperator
    {
        $operator = $this->repository->getOperatorByUserId($userId);
        if (!$operator) {
            $operator = new WarehouseOperator(
                uniqid('op_'),
                $userId,
                $tenantId,
                0.0,
                0.0,
                100.0,
                new \DateTimeImmutable(),
                new \DateTimeImmutable()
            );
            $this->repository->saveOperator($operator);
        }
        return $operator;
    }

    public function logTaskPerformance(
        string $operatorId,
        string $tenantId,
        string $taskType,
        string $locationId,
        int $durationSeconds,
        float $traversalDistanceMeters = 0.0
    ): void {
        $performance = new TaskPerformance(
            uniqid('tp_'),
            $operatorId,
            $tenantId,
            $taskType,
            $locationId,
            $traversalDistanceMeters,
            $durationSeconds,
            100.0,
            new \DateTimeImmutable()
        );
        $this->repository->saveTaskPerformance($performance);
    }
}
