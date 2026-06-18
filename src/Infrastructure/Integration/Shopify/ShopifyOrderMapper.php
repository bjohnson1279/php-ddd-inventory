<?php

namespace InventoryApp\Infrastructure\Integration\Shopify;

use InventoryApp\Application\Inventory\UseCases\ProcessSaleBatch;
use InventoryApp\Application\Inventory\UseCases\ProcessReturnBatch;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;

/**
 * Translates raw Shopify webhook payloads into our Inventory domain use cases.
 *
 * Each Shopify webhook topic maps to one or more domain operations:
 *   - orders/paid       → ProcessSaleBatch (all valid line items at once)
 *   - refunds/create    → ProcessReturnBatch (all valid refund line items at once)
 */
class ShopifyOrderMapper
{
    private ProcessSaleBatch $processSaleBatch;
    private ProcessReturnBatch $processReturnBatch;
    private ShopifyMappingRepository $mappings;
    private string $defaultLocationId;

    public function __construct(
        ProcessSaleBatch $processSaleBatch,
        ProcessReturnBatch $processReturnBatch,
        ShopifyMappingRepository $mappings,
        string $defaultLocationId = 'LOC-STOREFRONT'
    ) {
        $this->processSaleBatch  = $processSaleBatch;
        $this->processReturnBatch = $processReturnBatch;
        $this->mappings          = $mappings;
        $this->defaultLocationId = $defaultLocationId;
    }

    /**
     * Handle an `orders/paid` webhook payload.
     * Line items are collected and passed to ProcessSaleBatch.
     *
     * @param array $payload Decoded JSON from Shopify
     */
    public function handleOrderPaid(array $payload): void
    {
        $orderId = (string) ($payload['id'] ?? 'SHOPIFY-UNKNOWN');
        $batchItems = [];

        // Preload location IDs and SKUs to avoid N+1 queries
        $locationIds = [];
        $skus = [];
        foreach ($payload['line_items'] ?? [] as $item) {
            if (!empty($item['location_id'])) {
                $locationIds[] = (string) $item['location_id'];
            }
            if (!empty($item['sku'])) {
                $skus[] = (string) $item['sku'];
            }
        }
        if (!empty($locationIds)) {
            $this->mappings->preloadLocationIds(array_unique($locationIds));
        }
        if (!empty($skus)) {
            $this->mappings->preloadSkus(array_unique($skus));
        }

        foreach ($payload['line_items'] ?? [] as $item) {
            $sku      = $item['sku']      ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            // Skip items without a SKU or with zero quantity
            if (!$sku || $quantity <= 0) {
                continue;
            }

            // Determine location: prefer Shopify location_id if present
            $locationId = $this->resolveLocationId($item);

            $batchItems[] = [
                'sku' => $sku,
                'location' => $locationId,
                'quantity' => $quantity
            ];
        }

        if (!empty($batchItems)) {
            $this->processSaleBatch->execute($batchItems, $orderId);
        }
    }

    /**
     * Handle a `refunds/create` webhook payload.
     * Refund line items are collected and passed to ProcessReturnBatch.
     *
     * @param array $payload Decoded JSON from Shopify
     */
    public function handleRefundCreated(array $payload): void
    {
        $orderId = 'SHOPIFY-REFUND-' . ($payload['id'] ?? 'UNKNOWN');
        $batchItems = [];

        // Preload location IDs and SKUs to avoid N+1 queries
        $locationIds = [];
        $skus = [];
        foreach ($payload['refund_line_items'] ?? [] as $refundItem) {
            $lineItem = $refundItem['line_item'] ?? [];
            if (!empty($lineItem['location_id'])) {
                $locationIds[] = (string) $lineItem['location_id'];
            }
            if (!empty($lineItem['sku'])) {
                $skus[] = (string) $lineItem['sku'];
            }
        }
        if (!empty($locationIds)) {
            $this->mappings->preloadLocationIds(array_unique($locationIds));
        }
        if (!empty($skus)) {
            $this->mappings->preloadSkus(array_unique($skus));
        }

        foreach ($payload['refund_line_items'] ?? [] as $refundItem) {
            $lineItem = $refundItem['line_item'] ?? [];
            $sku      = $lineItem['sku'] ?? null;
            $quantity = (int) ($refundItem['quantity'] ?? 0);

            if (!$sku || $quantity <= 0) {
                continue;
            }

            $condition  = $this->resolveCondition($refundItem);
            $locationId = $this->resolveLocationId($lineItem);

            $batchItems[] = [
                'sku' => $sku,
                'location' => $locationId,
                'quantity' => $quantity,
                'condition' => $condition
            ];
        }

        if (!empty($batchItems)) {
            $this->processReturnBatch->execute($batchItems, $orderId);
        }
    }

    /**
     * Map Shopify's restock_type to our Condition value object string.
     *
     *  - 'return'     → NEW (restocked as sellable)
     *  - 'no_restock' → DAMAGED (not going back to shelf)
     *  - anything else → OPEN_BOX (needs inspection before resale)
     */
    private function resolveCondition(array $refundItem): string
    {
        $restockType = $refundItem['restock_type'] ?? '';

        return match ($restockType) {
            'return'     => Condition::NEW,
            'no_restock' => Condition::DAMAGED,
            default      => Condition::OPEN_BOX,
        };
    }

    /**
     * Map a Shopify location_id to our internal LocationId string via the
     * persisted shopify_location_mappings table. Falls back to the default.
     */
    private function resolveLocationId(array $item): string
    {
        $shopifyLocId = (string) ($item['location_id'] ?? '');

        if ($shopifyLocId !== '') {
            $mapped = $this->mappings->findLocationId($shopifyLocId);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return $this->defaultLocationId;
    }
}
