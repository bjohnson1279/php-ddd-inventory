<?php
namespace InventoryApp\Application\Integration\Amazon;

class AmazonIntegration
{
    private string $sellerId;
    private string $mwsAuthToken;
    private string $marketplaceId;

    public function __construct(string $sellerId, string $mwsAuthToken, string $marketplaceId)
    {
        $this->sellerId = $sellerId;
        $this->mwsAuthToken = $mwsAuthToken;
        $this->marketplaceId = $marketplaceId;
    }

    public function syncInventory(): void
    {
        // Scaffold
    }

    public function ingestOrder(array $payload): void
    {
        // Scaffold
    }
}
