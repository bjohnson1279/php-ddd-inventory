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

    public function test_it_throws_exception_for_empty_shipment_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipment ID cannot be empty');

        new PurchaseShippingLabelResult(
            '',
            'track_456',
            'http://example.com/label.pdf',
            1500
        );
    }

    public function test_it_throws_exception_for_whitespace_only_shipment_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipment ID cannot be empty');

        new PurchaseShippingLabelResult(
            '   ',
            'track_456',
            'http://example.com/label.pdf',
            1500
        );
    }

    public function test_it_throws_exception_for_empty_tracking_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tracking number cannot be empty');

        new PurchaseShippingLabelResult(
            'ship_123',
            '',
            'http://example.com/label.pdf',
            1500
        );
    }

    public function test_it_throws_exception_for_empty_label_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Label URL cannot be empty');

        new PurchaseShippingLabelResult(
            'ship_123',
            'track_456',
            '',
            1500
        );
    }

    public function test_it_throws_exception_for_negative_rate_cents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate cents cannot be negative');

        new PurchaseShippingLabelResult(
            'ship_123',
            'track_456',
            'http://example.com/label.pdf',
            -1
        );
    }
}
