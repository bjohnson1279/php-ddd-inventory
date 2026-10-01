<?php

namespace App\Domain\Integrations\Adapters;

class ShopifyIntegration implements BaseChannelAdapter
{
    private string $channelId;

    public function __construct(string $channelId)
    {
        $this->channelId = $channelId;
    }

    public function syncInventory(string $sku, int $availableQuantity): bool
    {
        return true;
    }

    public function ingestOrder(array $payload): ExternalOrder
    {
        if (!isset($payload['id']) || !isset($payload['line_items'])) {
            throw new \InvalidArgumentException('Invalid Shopify order payload');
        }

        $items = [];
        foreach ($payload['line_items'] as $item) {
            $items[] = [
                'sku' => $item['sku'],
                'quantity' => $item['quantity'],
                'unitPriceCents' => (int) str_replace('.', '', $item['price'])
            ];
        }

        $shippingAddress = $payload['shipping_address']['address1'] ?? '';

        return new ExternalOrder(
            (string) $payload['id'],
            $this->channelId,
            $items,
            $shippingAddress,
            new \DateTimeImmutable($payload['created_at'])
        );
    }

    public function pushFulfillmentStatus(string $externalOrderId, string $trackingNumber, string $carrier): bool
    {
        return true;
    }
}
