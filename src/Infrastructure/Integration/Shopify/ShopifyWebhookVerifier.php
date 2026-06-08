<?php

namespace InventoryApp\Infrastructure\Integration\Shopify;

/**
 * Verifies that an incoming webhook payload genuinely came from Shopify
 * by comparing the HMAC-SHA256 signature in the header against a locally
 * computed digest of the raw request body.
 */
class ShopifyWebhookVerifier
{
    private string $webhookSecret;

    public function __construct(string $webhookSecret)
    {
        $this->webhookSecret = $webhookSecret;
    }

    public function verify(string $rawPayload, string $hmacHeader): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        $computed = base64_encode(
            hash_hmac('sha256', $rawPayload, $this->webhookSecret, true)
        );

        return hash_equals($computed, $hmacHeader);
    }
}
