<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
use InventoryApp\Application\Ports\CarrierRate;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;
use Exception;
use DomainException;

class ShippingMockPhpStream
{
    public $context;
    private int $position = 0;
    public static string $data = '';

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        return true;
    }

    public function stream_read($count)
    {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat()
    {
        return [];
    }

}

class ShippingControllerTest extends TestCase
{
    private ShippingController $controller;
    private $calculateShippingRatesMock;
    private $requestMock;
    private $purchaseLabelMock;

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
            if ($key === 'sku') return 'TEST-SKU';
            if ($key === 'quantity') return '2';

        $rates = [
            new CarrierRate('UPS', 1500, 3),
            new CarrierRate('FedEx', 1200, 5)
        ];

        $this->calculateShippingRatesMock->expects($this->once())
            ->method('execute')
            ->with('TEST-SKU', 2, '123 Main St')
            ->willReturn($rates);


        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertStringContainsString('UPS', $content);
        $this->assertStringContainsString('1500', $content);
        $this->assertStringContainsString('FedEx', $content);
        $this->assertStringContainsString('1200', $content);
    }

    public function test_get_rates_returns_400_on_domain_exception(): void
    {
            if ($key === 'sku') return 'INVALID-SKU';

            ->willThrowException(new \DomainException('Invalid SKU provided'));



        $this->assertStringContainsString('Invalid SKU provided', $content);
        $this->assertStringContainsString('DomainException', $content);
    }

    public function test_get_rates_returns_500_on_generic_exception(): void
    {

            ->willThrowException(new \Exception('Database connection failed'));


        $this->assertEquals(500, $response->getStatusCode());

        $this->assertStringContainsString('An internal server error occurred', $content);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->purchaseLabelMock = $this->createMock(PurchaseShippingLabel::class);
        $_SERVER['auth.tenant_id'] = 'test-tenant';

        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_register('php', ShippingMockPhpStream::class);
        ShippingMockPhpStream::$data = '';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.tenant_id']);
        stream_wrapper_restore('php');
    }

    public function testPurchaseLabelSuccessReturns201(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TSHIRT-L-RED',
            'quantity' => 10,
            'destinationAddress' => '123 Test St, NY',
            'carrier' => 'FEDEX',
            'locationId' => 'LOC-1'
        ]);

        $mockResult = new PurchaseShippingLabelResult(
            'ship-123',
            'TRACK999',
            'http://label.url',
            1500
        );

        $this->purchaseLabelMock->expects($this->once())
            ->with(
                'TSHIRT-L-RED',
                10,
                '123 Test St, NY',
                'FEDEX',
                'LOC-1',
                'test-tenant'
            )
            ->willReturn($mockResult);

        $response = $this->controller->purchaseLabel($this->requestMock, $this->purchaseLabelMock);

        $this->assertEquals(201, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Shipping label purchased successfully.', $content['message']);
        $this->assertEquals('ship-123', $content['shipmentId']);
        $this->assertEquals('TRACK999', $content['trackingNumber']);
        $this->assertEquals('http://label.url', $content['labelUrl']);
        $this->assertEquals(1500, $content['rateCents']);
    }

    public function testPurchaseLabelMissingParametersReturns400(): void
    {
            // Missing quantity

        $this->purchaseLabelMock->expects($this->never())->method('execute');



        $this->assertEquals('Missing required parameters for shipping label purchase.', $content['error']);
    }

    public function testPurchaseLabelDomainExceptionReturns400(): void
    {

            ->willThrowException(new DomainException('Insufficient stock for SKU TSHIRT-L-RED'));



        $this->assertEquals('Insufficient stock for SKU TSHIRT-L-RED', $content['error']);
        $this->assertEquals('DomainException', $content['type']);
    }

    public function testPurchaseLabelInternalErrorReturns500(): void
    {

            ->willThrowException(new Exception('Database connection failed'));



        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
