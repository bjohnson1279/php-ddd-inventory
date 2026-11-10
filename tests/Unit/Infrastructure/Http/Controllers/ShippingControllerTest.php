<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;

class ShippingControllerTest extends TestCase
{
    private ShippingController $controller;
    private $calculateShippingRatesMock;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
        $this->calculateShippingRatesMock = $this->createMock(CalculateShippingRates::class);
    }

    /**
     * @dataProvider emptyParamsDataProvider
     */
    public function test_get_rates_returns_400_when_required_params_are_empty(?string $sku, ?string $address): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('query')
            ->willReturnCallback(function (string $key, $default = null) use ($sku, $address) {
                if ($key === 'sku') {
                    return $sku;
                }
                if ($key === 'address') {
                    return $address;
                }
                if ($key === 'quantity') {
                    return null;
                }
                return $default;
            });

        $this->calculateShippingRatesMock->expects($this->never())
            ->method('execute');

        $response = $this->controller->getRates($requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Missing required query parameters: sku and address.', $content['error']);
    }

    public function emptyParamsDataProvider(): array
    {
        return [
            'both null' => [null, null],
            'both empty string' => ['', ''],
            'missing sku (null)' => [null, '1600 Amphitheatre Pkwy'],
            'empty sku' => ['', '1600 Amphitheatre Pkwy'],
            'missing address (null)' => ['SKU-123', null],
            'empty address' => ['SKU-123', ''],
        ];
    }
}
