<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
use InventoryApp\Application\Ports\CarrierRate;
use InventoryApp\Infrastructure\Http\Response;

class ShippingControllerTest extends TestCase
{
    private ShippingController $controller;
    private $calculateShippingRatesMock;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
        $this->calculateShippingRatesMock = $this->createMock(CalculateShippingRates::class);
    }

    public function test_get_rates_returns_400_when_missing_sku_or_address(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('query')->willReturnCallback(function($key) {
            if ($key === 'sku') return null;
            if ($key === 'quantity') return '1';
            if ($key === 'address') return '123 Main St';
            return null;
        });

        $response = $this->controller->getRates($requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing required query parameters', $response->getContent());
    }

    public function test_get_rates_returns_200_with_rates_on_success(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('query')->willReturnCallback(function($key) {
            if ($key === 'sku') return 'TEST-SKU';
            if ($key === 'quantity') return '2';
            if ($key === 'address') return '123 Main St';
            return null;
        });

        $rates = [
            new CarrierRate('UPS', 1500, 3),
            new CarrierRate('FedEx', 1200, 5)
        ];

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->with('TEST-SKU', 2, '123 Main St')
            ->willReturn($rates);

        $response = $this->controller->getRates($requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertStringContainsString('UPS', $content);
        $this->assertStringContainsString('1500', $content);
        $this->assertStringContainsString('FedEx', $content);
        $this->assertStringContainsString('1200', $content);
    }

    public function test_get_rates_returns_400_on_domain_exception(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('query')->willReturnCallback(function($key) {
            if ($key === 'sku') return 'INVALID-SKU';
            if ($key === 'quantity') return '1';
            if ($key === 'address') return '123 Main St';
            return null;
        });

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \DomainException('Invalid SKU provided'));

        $response = $this->controller->getRates($requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertStringContainsString('Invalid SKU provided', $content);
        $this->assertStringContainsString('DomainException', $content);
    }

    public function test_get_rates_returns_500_on_generic_exception(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->method('query')->willReturnCallback(function($key) {
            if ($key === 'sku') return 'TEST-SKU';
            if ($key === 'quantity') return '1';
            if ($key === 'address') return '123 Main St';
            return null;
        });

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database connection failed'));

        $response = $this->controller->getRates($requestMock, $this->calculateShippingRatesMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertStringContainsString('An internal server error occurred', $content);
    }
}
