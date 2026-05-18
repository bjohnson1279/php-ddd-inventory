<?php

namespace Tests\Unit\Infrastructure\Integration\Shopify;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyWebhookVerifier;

class ShopifyWebhookVerifierTest extends TestCase
{
    private string $secret = 'my-shopify-webhook-secret';

    private function makeHmac(string $payload): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $this->secret, true));
    }

    public function testValidHmacReturnsTrue(): void
    {
        $payload = '{"id":12345,"line_items":[]}';
        $hmac    = $this->makeHmac($payload);

        $verifier = new ShopifyWebhookVerifier($this->secret);
        $this->assertTrue($verifier->verify($payload, $hmac));
    }

    public function testTamperedPayloadReturnsFalse(): void
    {
        $payload  = '{"id":12345,"line_items":[]}';
        $hmac     = $this->makeHmac($payload);
        $tampered = '{"id":99999,"line_items":[]}'; // different payload

        $verifier = new ShopifyWebhookVerifier($this->secret);
        $this->assertFalse($verifier->verify($tampered, $hmac));
    }

    public function testWrongSecretReturnsFalse(): void
    {
        $payload  = '{"id":12345}';
        $hmac     = base64_encode(hash_hmac('sha256', $payload, 'wrong-secret', true));

        $verifier = new ShopifyWebhookVerifier($this->secret);
        $this->assertFalse($verifier->verify($payload, $hmac));
    }

    public function testEmptyHmacReturnsFalse(): void
    {
        $verifier = new ShopifyWebhookVerifier($this->secret);
        $this->assertFalse($verifier->verify('{"id":1}', ''));
    }
}
