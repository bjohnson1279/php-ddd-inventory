<?php

namespace Tests\Unit\Application\Ports;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Ports\LabelResult;

class LabelResultTest extends TestCase
{
    public function testLabelResultInitialization()
    {
        $trackingNumber = '1Z9999999999999999';
        $labelUrl = 'https://example.com/label.pdf';
        $rateCents = 1500;

        $labelResult = new LabelResult($trackingNumber, $labelUrl, $rateCents);

        $this->assertSame($trackingNumber, $labelResult->trackingNumber);
        $this->assertSame($labelUrl, $labelResult->labelUrl);
        $this->assertSame($rateCents, $labelResult->rateCents);
    }

    public function testLabelResultToArray()
    {
        $trackingNumber = '1Z9999999999999999';
        $labelUrl = 'https://example.com/label.pdf';
        $rateCents = 1500;

        $labelResult = new LabelResult($trackingNumber, $labelUrl, $rateCents);

        $expectedArray = [
            'trackingNumber' => $trackingNumber,
            'labelUrl' => $labelUrl,
            'rateCents' => $rateCents,
        ];

        $this->assertSame($expectedArray, $labelResult->toArray());
    }
}
