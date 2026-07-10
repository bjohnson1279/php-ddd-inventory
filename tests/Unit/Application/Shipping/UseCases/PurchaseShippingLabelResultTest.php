<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;
use PHPUnit\Framework\TestCase;

class PurchaseShippingLabelResultTest extends TestCase
{
    public function test_it_sets_properties_correctly(): void
    {
        $shipmentId = 'ship_123';
        $trackingNumber = 'track_456';
        $labelUrl = 'http://example.com/label.pdf';
        $rateCents = 1500;

        $result = new PurchaseShippingLabelResult(
            $shipmentId,
            $trackingNumber,
            $labelUrl,
            $rateCents
        );

        $this->assertEquals($shipmentId, $result->shipmentId);
        $this->assertEquals($trackingNumber, $result->trackingNumber);
        $this->assertEquals($labelUrl, $result->labelUrl);
        $this->assertEquals($rateCents, $result->rateCents);
    }

    public function test_it_converts_to_array_correctly(): void
    {
        $shipmentId = 'ship_123';
        $trackingNumber = 'track_456';
        $labelUrl = 'http://example.com/label.pdf';
        $rateCents = 1500;

        $result = new PurchaseShippingLabelResult(
            $shipmentId,
            $trackingNumber,
            $labelUrl,
            $rateCents
        );

        $expectedArray = [
            'shipmentId' => $shipmentId,
            'trackingNumber' => $trackingNumber,
            'labelUrl' => $labelUrl,
            'rateCents' => $rateCents,
        ];

        $this->assertEquals($expectedArray, $result->toArray());
    }
}
