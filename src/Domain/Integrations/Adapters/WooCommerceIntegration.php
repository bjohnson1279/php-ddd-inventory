<?php

namespace App\Domain\Integrations\Adapters;

class WooCommerceIntegration implements BaseChannelAdapter
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
            throw new \InvalidArgumentException('Invalid WooCommerce order payload');
        }

        $items = [];
        foreach ($payload['line_items'] as $item) {
            $items[] = [
                'sku' => $item['sku'],
                'quantity' => $item['quantity'],
                'unitPriceCents' => (int) round((float)$item['price'] * 100)
            ];
        }

        $shippingAddress = $payload['shipping']['address_1'] ?? '';

        return new ExternalOrder(
            (string) $payload['id'],
            $this->channelId,
            $items,
            $shippingAddress,
            new \DateTimeImmutable($payload['date_created_gmt'])
        );
    }

    public function pushFulfillmentStatus(string $externalOrderId, string $trackingNumber, string $carrier): bool
    {
        return true;
    }
}
