<?php

namespace InventoryApp\Infrastructure\Integration\Shopify;

use InventoryApp\Application\Inventory\UseCases\ProcessSale;
use InventoryApp\Application\Inventory\UseCases\ProcessReturn;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;

/**
 * Translates raw Shopify webhook payloads into our Inventory domain use cases.
 *
 * Each Shopify webhook topic maps to one or more domain operations:
 *   - orders/paid       → ProcessSale (per line item, per location)
 *   - refunds/create    → ProcessReturn (with condition routing)
 */
class ShopifyOrderMapper
{
    private ProcessSale $processSale;
    private ProcessReturn $processReturn;
    private ShopifyMappingRepository $mappings;
    private string $defaultLocationId;

    public function __construct(
        ProcessSale $processSale,
        ProcessReturn $processReturn,
        ShopifyMappingRepository $mappings,
        string $defaultLocationId = 'LOC-STOREFRONT'
    ) {
        $this->processSale       = $processSale;
        $this->processReturn     = $processReturn;
        $this->mappings          = $mappings;
        $this->defaultLocationId = $defaultLocationId;
    }

    /**
     * Handle an `orders/paid` webhook payload.
     * Each line item becomes one ProcessSale call keyed by its SKU.
     *
     * @param array $payload Decoded JSON from Shopify
     */
    public function handleOrderPaid(array $payload): void
    {
        $orderId = (string) ($payload['id'] ?? 'SHOPIFY-UNKNOWN');
        $itemsToProcess = [];

        foreach ($payload['line_items'] ?? [] as $item) {
            $sku      = $item['sku']      ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            // Skip items without a SKU or with zero quantity
            if (!$sku || $quantity <= 0) {
                continue;
            }

            // Determine location: prefer Shopify location_id if present
            $locationId = $this->resolveLocationId($item);

            $itemsToProcess[] = [
                'sku'      => $sku,
                'location' => $locationId,
                'quantity' => $quantity,
            ];
        }

        if (!empty($itemsToProcess)) {
            $this->processSale->executeBulk($itemsToProcess, $orderId);
        }
    }

    /**
     * Handle a `refunds/create` webhook payload.
     * Refund line items are routed to ProcessReturn with a Condition derived
     * from any restock flag and Shopify's restock_type field.
     *
     * @param array $payload Decoded JSON from Shopify
     */
    public function handleRefundCreated(array $payload): void
    {
        $orderId = 'SHOPIFY-REFUND-' . ($payload['id'] ?? 'UNKNOWN');
        $itemsToProcess = [];

        foreach ($payload['refund_line_items'] ?? [] as $refundItem) {
            $lineItem = $refundItem['line_item'] ?? [];
            $sku      = $lineItem['sku'] ?? null;
            $quantity = (int) ($refundItem['quantity'] ?? 0);

            if (!$sku || $quantity <= 0) {
                continue;
            }

            $condition  = $this->resolveCondition($refundItem);
            $locationId = $this->resolveLocationId($lineItem);

            $itemsToProcess[] = [
                'sku'       => $sku,
                'location'  => $locationId,
                'quantity'  => $quantity,
                'condition' => $condition,
            ];
        }

        if (!empty($itemsToProcess)) {
            $this->processReturn->executeBulk($itemsToProcess, $orderId);
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
