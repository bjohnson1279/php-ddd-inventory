<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
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
    private $requestMock;
    private $purchaseLabelMock;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
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
            ->method('execute')
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
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TSHIRT-L-RED',
            // Missing quantity
            'destinationAddress' => '123 Test St, NY',
            'carrier' => 'FEDEX',
        ]);

        $this->purchaseLabelMock->expects($this->never())->method('execute');

        $response = $this->controller->purchaseLabel($this->requestMock, $this->purchaseLabelMock);

        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Missing required parameters for shipping label purchase.', $content['error']);
    }

    public function testPurchaseLabelDomainExceptionReturns400(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TSHIRT-L-RED',
            'quantity' => 10,
            'destinationAddress' => '123 Test St, NY',
            'carrier' => 'FEDEX',
        ]);

        $this->purchaseLabelMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new DomainException('Insufficient stock for SKU TSHIRT-L-RED'));

        $response = $this->controller->purchaseLabel($this->requestMock, $this->purchaseLabelMock);

        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Insufficient stock for SKU TSHIRT-L-RED', $content['error']);
        $this->assertEquals('DomainException', $content['type']);
    }

    public function testPurchaseLabelInternalErrorReturns500(): void
    {
        ShippingMockPhpStream::$data = json_encode([
            'sku' => 'TSHIRT-L-RED',
            'quantity' => 10,
            'destinationAddress' => '123 Test St, NY',
            'carrier' => 'FEDEX',
        ]);

        $this->purchaseLabelMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception('Database connection failed'));

        $response = $this->controller->purchaseLabel($this->requestMock, $this->purchaseLabelMock);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
