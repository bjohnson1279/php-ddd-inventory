<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Workforce\Repositories;

use InventoryApp\Domain\Workforce\Entities\WarehouseOperator;
use InventoryApp\Domain\Workforce\Entities\LaborShift;
use InventoryApp\Domain\Workforce\Entities\TaskPerformance;

interface WorkforceRepositoryInterface
{
    public function getOperatorByUserId(string $userId): ?WarehouseOperator;
    public function saveOperator(WarehouseOperator $operator): void;

    public function saveLaborShift(LaborShift $shift): void;
    /** @return LaborShift[] */
    public function getShiftsForOperator(string $operatorId, \DateTimeImmutable $start, \DateTimeImmutable $end): array;

    public function saveTaskPerformance(TaskPerformance $performance): void;
    /** @return WarehouseOperator[] */
    public function getLeaderboard(string $tenantId, int $limit): array;
}
