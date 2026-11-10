<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
use InventoryApp\Application\Ports\CarrierRate;
use InvalidArgumentException;
use DomainException;
use RuntimeException;

class ShippingControllerTest extends TestCase
{
    private ShippingController $controller;
    private $calculateShippingRatesMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
        $this->calculateShippingRatesMock = $this->createMock(CalculateShippingRates::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
    }

    public function testGetRatesReturns200WithDefaultQuantityWhenQuantityNotProvided(): void
    {
        $sku = 'TEST-SKU';
        $address = '123 Test St, Test City, TS 12345';

        $this->requestMock->expects($this->exactly(3))
            ->method('query')
            ->willReturnMap([
                ['sku', null, $sku],
                ['quantity', null, null],
                ['address', null, $address],
            ]);

        $rates = [
            new CarrierRate('FedEx', 1200, 2),
            new CarrierRate('UPS', 1500, 3),
        ];

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->with($sku, 1, $address)
            ->willReturn($rates);

        $response = $this->controller->getRates($this->requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertEquals([
            [
                'carrier' => 'FedEx',
                'rateCents' => 1200,
                'estimatedDays' => 2,
            ],
            [
                'carrier' => 'UPS',
                'rateCents' => 1500,
                'estimatedDays' => 3,
            ]
        ], $data);
    }

    public function testGetRatesReturns200WithParsedCustomQuantity(): void
    {
        $sku = 'TEST-SKU';
        $quantityVal = '5';
        $address = '456 Sample Ave, City, ST 67890';

        $this->requestMock->expects($this->exactly(3))
            ->method('query')
            ->willReturnMap([
                ['sku', null, $sku],
                ['quantity', null, $quantityVal],
                ['address', null, $address],
            ]);

        $rates = [
            new CarrierRate('USPS', 850, 4),
        ];

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->with($sku, 5, $address)
            ->willReturn($rates);

        $response = $this->controller->getRates($this->requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals([
            [
                'carrier' => 'USPS',
                'rateCents' => 850,
                'estimatedDays' => 4,
            ]
        ], $data);
    }

    public function testGetRatesReturns400WhenSkuIsMissing(): void
    {
        $this->requestMock->expects($this->exactly(3))
            ->method('query')
            ->willReturnMap([
                ['sku', null, ''],
                ['quantity', null, '2'],
                ['address', null, '123 Test St'],
            ]);

        $this->calculateShippingRatesMock->expects($this->never())
            ->method('execute');

        $response = $this->controller->getRates($this->requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Missing required query parameters: sku and address.', $data['error']);
    }

    public function testGetRatesReturns400WhenAddressIsMissing(): void
    {
        $this->requestMock->expects($this->exactly(3))
            ->method('query')
            ->willReturnMap([
                ['sku', null, 'TEST-SKU'],
                ['quantity', null, '2'],
                ['address', null, null],
            ]);

        $this->calculateShippingRatesMock->expects($this->never())
            ->method('execute');

        $response = $this->controller->getRates($this->requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Missing required query parameters: sku and address.', $data['error']);
    }

    public function testGetRatesReturns400OnDomainOrValidationException(): void
    {
        $sku = 'TEST-SKU';
        $address = '123 Test St';

        $this->requestMock->expects($this->exactly(3))
            ->method('query')
            ->willReturnMap([
                ['sku', null, $sku],
                ['quantity', null, '1'],
                ['address', null, $address],
            ]);

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new InvalidArgumentException('Invalid destination address format'));

        $response = $this->controller->getRates($this->requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid destination address format', $data['error']);
        $this->assertEquals('InvalidArgumentException', $data['type']);
    }

    public function testGetRatesReturns500OnUnexpectedException(): void
    {
        $sku = 'TEST-SKU';
        $address = '123 Test St';

        $this->requestMock->expects($this->exactly(3))
            ->method('query')
            ->willReturnMap([
                ['sku', null, $sku],
                ['quantity', null, '1'],
                ['address', null, $address],
            ]);

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new RuntimeException('Carrier API is unavailable'));

        $response = $this->controller->getRates($this->requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $data['error']);
    }
}
