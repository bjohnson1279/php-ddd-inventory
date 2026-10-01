<?php

namespace Tests\Unit\Domain\Integrations;

use PHPUnit\Framework\TestCase;
use App\Domain\Integrations\Adapters\ShopifyIntegration;
use App\Domain\Integrations\Adapters\AmazonSPIntegration;
use App\Domain\Integrations\Adapters\WooCommerceIntegration;

class OmnichannelAdaptersTest extends TestCase
{
    public function testShopifyIntegration()
    {
        $adapter = new ShopifyIntegration('shopify1');
        $payload = [
            'id' => 12345,
            'created_at' => '2023-01-01T12:00:00Z',
            'shipping_address' => ['address1' => '123 Main St'],
            'line_items' => [
                ['sku' => 'SKU1', 'quantity' => 2, 'price' => '19.99']
            ]
        ];

        $order = $adapter->ingestOrder($payload);
        $this->assertEquals('12345', $order->externalOrderId);
        $this->assertEquals(1999, $order->items[0]['unitPriceCents']);
        $this->assertEquals('123 Main St', $order->shippingAddress);
    }

    public function testAmazonSPIntegration()
    {
        $adapter = new AmazonSPIntegration('amazon1');
        $payload = [
            'AmazonOrderId' => 'AMZ-123',
            'PurchaseDate' => '2023-01-01T12:00:00Z',
            'ShippingAddress' => ['AddressLine1' => '456 Oak St'],
            'OrderItems' => [
                ['SellerSKU' => 'SKU2', 'QuantityOrdered' => 1, 'ItemPrice' => ['Amount' => '29.99']]
            ]
        ];

        $order = $adapter->ingestOrder($payload);
        $this->assertEquals('AMZ-123', $order->externalOrderId);
        $this->assertEquals(2999, $order->items[0]['unitPriceCents']);
    }

    public function testWooCommerceIntegration()
    {
        $adapter = new WooCommerceIntegration('woo1');
        $payload = [
            'id' => 999,
            'date_created_gmt' => '2023-01-01T12:00:00',
            'shipping' => ['address_1' => '789 Pine St'],
            'line_items' => [
                ['sku' => 'SKU3', 'quantity' => 3, 'price' => '9.99']
            ]
        ];

        $order = $adapter->ingestOrder($payload);
        $this->assertEquals('999', $order->externalOrderId);
        $this->assertEquals(999, $order->items[0]['unitPriceCents']);
    }
}
