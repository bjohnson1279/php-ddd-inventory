<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;
use PHPUnit\Framework\TestCase;

class PurchaseShippingLabelResultTest extends TestCase
{
    public function test_it_can_be_instantiated()
    {
        $result = new PurchaseShippingLabelResult(
            'ship-123',
            'TRACK999',
            'https://example.com/label.pdf',
            1500
        );

        $this->assertEquals('ship-123', $result->shipmentId);
        $this->assertEquals('TRACK999', $result->trackingNumber);
        $this->assertEquals('https://example.com/label.pdf', $result->labelUrl);
        $this->assertEquals(1500, $result->rateCents);
    }

    public function test_it_can_be_converted_to_array()
    {
        $result = new PurchaseShippingLabelResult(
            'ship-123',
            'TRACK999',
            'https://example.com/label.pdf',
            1500
        );

        $expectedArray = [
            'shipmentId' => 'ship-123',
            'trackingNumber' => 'TRACK999',
            'labelUrl' => 'https://example.com/label.pdf',
            'rateCents' => 1500,
        ];

        $this->assertEquals($expectedArray, $result->toArray());
    }
}
