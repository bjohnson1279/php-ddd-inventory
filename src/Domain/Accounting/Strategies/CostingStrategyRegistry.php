<?php

namespace InventoryApp\Domain\Accounting\Strategies;

use InventoryApp\Domain\Accounting\Enums\CostingMethod;
use RuntimeException;

class CostingStrategyRegistry
{
    private static array $strategies = [];

    private static function initialize(): void
    {
        if (empty(self::$strategies)) {
            self::$strategies = [
                CostingMethod::FIFO->value => new FifoCostingStrategy(),
                CostingMethod::LIFO->value => new LifoCostingStrategy(),
                CostingMethod::WeightedAverageCost->value => new WeightedAverageCostingStrategy(),
            ];
        }
    }

    public static function get(CostingMethod $method): CostingStrategyInterface
    {
        self::initialize();
        $strategy = self::$strategies[$method->value] ?? null;
        if (!$strategy) {
            throw new RuntimeException("Unsupported costing method: " . $method->name);
        }
        return $strategy;
    }
}
