<?php

namespace InventoryApp\Domain\Shipping\Services;

class CrossDockingEngine
{
    public static function evaluate(
        string $purchaseOrderId,
        array $inboundItems,
        array $backorders
    ): array {
        $opportunities = [];

        foreach ($inboundItems as $item) {
            $variantId = $item['variantId'];
            $inboundQty = $item['quantity'];

            $matching = array_filter($backorders, function ($b) use ($variantId) {
                return ($b['variantId'] ?? '') === $variantId;
            });

            usort($matching, function ($a, $b) {
                return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
            });

            if (count($matching) > 0) {
                $remainingInbound = $inboundQty;
                $assignedBackorders = [];

                foreach ($matching as $bo) {
                    if ($remainingInbound <= 0) break;
                    $assigned = min($remainingInbound, $bo['quantity']);
                    $assignedBackorders[] = [
                        'orderId' => $bo['orderId'],
                        'requiredQuantity' => $assigned,
                        'priority' => $bo['priority'] ?? 1
                    ];
                    $remainingInbound -= $assigned;
                }

                $totalAssigned = $inboundQty - $remainingInbound;
                $opportunities[] = [
                    'purchaseOrderId' => $purchaseOrderId,
                    'variantId' => $variantId,
                    'inboundQuantity' => $inboundQty,
                    'matchingBackorders' => $assignedBackorders,
                    'recommendedCrossDockQuantity' => $totalAssigned,
                    'destinationBay' => 'DOCK-OUTBOUND-BAY-01'
                ];
            }
        }

        return $opportunities;
    }
}
