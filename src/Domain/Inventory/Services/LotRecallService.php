<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Entities\LotBatch;

class LotRecallService
{
    public static function generateTraceabilityReport(
        LotBatch $lot,
        array $costLayers,
        array $fulfilledShipments
    ): array {
        $affectedOrders = array_map(function ($s) {
            return [
                'orderId' => $s['id'] ?? $s['orderId'] ?? 'order-unknown',
                'quantity' => $s['quantity'] ?? 1
            ];
        }, $fulfilledShipments);

        $customers = array_unique(array_filter(array_map(function ($s) {
            return $s['destinationAddress'] ?? $s['customerId'] ?? null;
        }, $fulfilledShipments)));

        return [
            'lotNumber' => $lot->lotNumber,
            'variantId' => $lot->variantId,
            'status' => $lot->status,
            'quarantineReason' => $lot->quarantineReason,
            'affectedCostLayersCount' => count($costLayers),
            'affectedOrders' => array_values($affectedOrders),
            'affectedCustomers' => array_values($customers)
        ];
    }
}
