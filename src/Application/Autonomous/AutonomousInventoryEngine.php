<?php

namespace App\Application\Autonomous;

class AutonomousInventoryEngine
{
    private string $mode;

    public function __construct(string $mode = 'HUMAN_IN_THE_LOOP')
    {
        $this->mode = mode;
    }

    public function evaluateStockLevels(array $items): array
    {
        $actions = [];

        foreach ($items as $item) {
            $currentStock = $item['current_stock'] ?? 0;
            $reorderPoint = $item['reorder_point'] ?? 10;

            if ($currentStock <= $reorderPoint) {
                $qty = ($reorderPoint * 2) - $currentStock;
                $actions[] = [
                    'sku' => $item['sku'] ?? 'UNKNOWN',
                    'action' => 'AUTO_PO',
                    'order_quantity' => $qty,
                    'mode' => $this->mode,
                    'status' => $this->mode === 'FULLY_AUTONOMOUS' ? 'EXECUTED' : 'DRAFT',
                ];
            }
        }

        return $actions;
    }
}
