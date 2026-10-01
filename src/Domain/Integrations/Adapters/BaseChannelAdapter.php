<?php

namespace App\Domain\Integrations\Adapters;

interface BaseChannelAdapter
{
    public function syncInventory(string $sku, int $availableQuantity): bool;
    public function ingestOrder(array $payload): ExternalOrder;
    public function pushFulfillmentStatus(string $externalOrderId, string $trackingNumber, string $carrier): bool;
}

class ExternalOrder
{
    public string $externalOrderId;
    public string $channelId;
    public array $items; // {sku, quantity, unitPriceCents}
    public string $shippingAddress;
    public \DateTimeImmutable $createdAt;

    public function __construct(string $externalOrderId, string $channelId, array $items, string $shippingAddress, \DateTimeImmutable $createdAt)
    {
        $this->externalOrderId = $externalOrderId;
        $this->channelId = $channelId;
        $this->items = $items;
        $this->shippingAddress = $shippingAddress;
        $this->createdAt = $createdAt;
    }
}
