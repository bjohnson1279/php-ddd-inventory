<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Shipping\UseCases\RouteOrder;
use InventoryApp\Domain\Shipping\Strategies\FulfillmentPlan;
use InventoryApp\Domain\Shipping\Strategies\FulfillmentAllocation;
use Exception;
use InvalidArgumentException;
use DomainException;

if (!class_exists(ShippingMockPhpStream::class)) {
    class ShippingMockPhpStream
    {
        public $context;
        private int $position = 0;
        public static string $data = '';

        public function stream_open($path, $mode, $options, &$opened_path): bool
        {
            $this->position = 0;
            return true;
        }

        public function stream_read($count): string
        {
            $ret = substr(self::$data, $this->position, $count);
            $this->position += strlen($ret);
            return $ret;
        }

        public function stream_eof(): bool
        {
            return $this->position >= strlen(self::$data);
        }

        public function stream_stat(): array
        {
            return [];
        }
    }
}

class ShippingControllerTest extends TestCase
{
    private ShippingController $controller;
    private $requestMock;
    private $routeOrderMock;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->routeOrderMock = $this->createMock(RouteOrder::class);

        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_register('php', ShippingMockPhpStream::class);
        ShippingMockPhpStream::$data = '';
    }

    protected function tearDown(): void
    {
        stream_wrapper_restore('php');
        ShippingMockPhpStream::$data = '';
    }

    public function testRouteOrderMissingRequiredFieldsReturns400(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => '',
            'quantity' => null,
            'destinationAddress' => ''
        ]);

        $response = $this->controller->routeOrder($this->requestMock, $this->routeOrderMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Missing required body fields: sku, quantity, and destinationAddress.', $content['error']);
    }

    public function testRouteOrderSuccessReturns200(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TEST-SKU',
            'quantity' => 2,
            'destinationAddress' => '123 Test St, NY',
            'strategyName' => 'MINIMIZE_COST'
        ]);

        $allocations = [
            new FulfillmentAllocation('LOC-1', 2)
        ];
        $plan = new FulfillmentPlan(
            allocations: $allocations,
            estimatedShippingCostCents: 500,
            totalDistanceKm: 12.5,
            splitCount: 1,
            score: 0.95
        );

        $this->routeOrderMock->expects($this->once())
            ->method('execute')
            ->with('TEST-SKU', 2, '123 Test St, NY', 'MINIMIZE_COST')
            ->willReturn($plan);

        $response = $this->controller->routeOrder($this->requestMock, $this->routeOrderMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('allocations', $content);
        $this->assertEquals([['locationId' => 'LOC-1', 'quantity' => 2]], $content['allocations']);
        $this->assertEquals(500, $content['estimatedShippingCostCents']);
        $this->assertEquals(12.5, $content['totalDistanceKm']);
        $this->assertEquals(1, $content['splitCount']);
        $this->assertEquals(0.95, $content['score']);
    }

    public function testRouteOrderGenericExceptionReturns500(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TEST-SKU',
            'quantity' => 2,
            'destinationAddress' => '123 Test St, NY'
        ]);

        $this->routeOrderMock->expects($this->once())
            ->method('execute')
            ->with('TEST-SKU', 2, '123 Test St, NY', null)
            ->willThrowException(new Exception('Database connection lost'));

        $response = $this->controller->routeOrder($this->requestMock, $this->routeOrderMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Failed to route order: Database connection lost', $content['error']);
    }

    public function testRouteOrderInvalidArgumentExceptionReturns400(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TEST-SKU',
            'quantity' => 2,
            'destinationAddress' => '123 Test St, NY'
        ]);

        $this->routeOrderMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new InvalidArgumentException('Invalid destination address'));

        $response = $this->controller->routeOrder($this->requestMock, $this->routeOrderMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Invalid destination address', $content['error']);
        $this->assertEquals('InvalidArgumentException', $content['type']);
    }

    public function testRouteOrderDomainExceptionReturns400(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TEST-SKU',
            'quantity' => 2,
            'destinationAddress' => '123 Test St, NY'
        ]);

        $this->routeOrderMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new DomainException('Insufficient stock across warehouses'));

        $response = $this->controller->routeOrder($this->requestMock, $this->routeOrderMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Insufficient stock across warehouses', $content['error']);
        $this->assertEquals('DomainException', $content['type']);
    }
}
