<?php
namespace InventoryApp\Application\Integration\WooCommerce;

class WooCommerceIntegration
{
    private string $storeUrl;
    private string $consumerKey;
    private string $consumerSecret;

    public function __construct(string $storeUrl, string $consumerKey, string $consumerSecret)
    {
        $this->storeUrl = $storeUrl;
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
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
