<?php

namespace App\Domain\Integrations\Adapters;

class AmazonSPIntegration implements BaseChannelAdapter
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
        if (!isset($payload['AmazonOrderId']) || !isset($payload['OrderItems'])) {
            throw new \InvalidArgumentException('Invalid Amazon SP-API order payload');
        }

        $items = [];
        foreach ($payload['OrderItems'] as $item) {
            $items[] = [
                'sku' => $item['SellerSKU'],
                'quantity' => $item['QuantityOrdered'],
                'unitPriceCents' => isset($item['ItemPrice']['Amount']) ? (int) str_replace('.', '', $item['ItemPrice']['Amount']) : 0
            ];
        }

        $shippingAddress = $payload['ShippingAddress']['AddressLine1'] ?? '';

        return new ExternalOrder(
            $payload['AmazonOrderId'],
            $this->channelId,
            $items,
            $shippingAddress,
            new \DateTimeImmutable($payload['PurchaseDate'])
        );
    }

    public function pushFulfillmentStatus(string $externalOrderId, string $trackingNumber, string $carrier): bool
    {
        return true;
    }
}
