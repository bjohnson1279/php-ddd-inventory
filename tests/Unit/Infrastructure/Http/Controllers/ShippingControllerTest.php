<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;
use InvalidArgumentException;
use DomainException;
use Exception;

class ShippingPhpStreamWrapper
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
    private $useCaseMock;
    private bool $wrapperRegistered = false;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->useCaseMock = $this->createMock(PurchaseShippingLabel::class);

        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_register('php', ShippingPhpStreamWrapper::class);
        $this->wrapperRegistered = true;

        ShippingPhpStreamWrapper::$data = '';
        unset($_SERVER['auth.tenant_id']);
    }

    protected function tearDown(): void
    {
        if ($this->wrapperRegistered) {
            stream_wrapper_restore('php');
            $this->wrapperRegistered = false;
        }

        ShippingPhpStreamWrapper::$data = '';
        unset($_SERVER['auth.tenant_id']);
        parent::tearDown();
    }

    public function testPurchaseLabelSuccessReturns201(): void
    {
        $payload = [
            'sku' => 'SKU-TEST-1',
            'quantity' => 2,
            'destinationAddress' => '123 Test St',
            'carrier' => 'UPS',
            'locationId' => 'LOC-1',
            'tenantId' => 'tenant-123'
        ];
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'tenant-123';

        $result = new PurchaseShippingLabelResult('ship-1', 'TRACK-123', 'http://label.url', 1500);

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->with('SKU-TEST-1', 2, '123 Test St', 'UPS', 'LOC-1', 'tenant-123')
            ->willReturn($result);

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Shipping label purchased successfully.', $content['message']);
        $this->assertEquals('ship-1', $content['shipmentId']);
        $this->assertEquals('TRACK-123', $content['trackingNumber']);
        $this->assertEquals('http://label.url', $content['labelUrl']);
        $this->assertEquals(1500, $content['rateCents']);
    }

    public function testPurchaseLabelUsesDefaultLocationAndServerTenantWhenOmitted(): void
    {
        $payload = [
            'sku' => 'SKU-TEST-1',
            'quantity' => 1,
            'destinationAddress' => '456 Main St',
            'carrier' => 'FedEx'
        ];
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'system';

        $result = new PurchaseShippingLabelResult('ship-2', 'TRACK-456', 'http://label2.url', 2000);

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->with('SKU-TEST-1', 1, '456 Main St', 'FedEx', 'default', 'system')
            ->willReturn($result);

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testPurchaseLabelUnauthorizedReturns403WhenTenantMismatch(): void
    {
        $payload = [
            'sku' => 'SKU-TEST-1',
            'quantity' => 1,
            'destinationAddress' => '123 Test St',
            'carrier' => 'UPS',
            'tenantId' => 'tenant-different'
        ];
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'tenant-current';

        $this->useCaseMock->expects($this->never())->method('execute');

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $content['error']);
    }

    public function testPurchaseLabelAllowsSystemUserToOperateOnAnyTenant(): void
    {
        $payload = [
            'sku' => 'SKU-TEST-1',
            'quantity' => 3,
            'destinationAddress' => '123 Test St',
            'carrier' => 'DHL',
            'locationId' => 'LOC-2',
            'tenantId' => 'tenant-target'
        ];
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'system';

        $result = new PurchaseShippingLabelResult('ship-3', 'TRACK-789', 'http://label3.url', 3000);

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->with('SKU-TEST-1', 3, '123 Test St', 'DHL', 'LOC-2', 'tenant-target')
            ->willReturn($result);

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertEquals(201, $response->getStatusCode());
    }

    /**
     * @dataProvider missingRequiredParametersProvider
     */
    public function testPurchaseLabelMissingParametersReturns400(array $payload): void
    {
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'system';

        $this->useCaseMock->expects($this->never())->method('execute');

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Missing required parameters for shipping label purchase.', $content['error']);
    }

    public function missingRequiredParametersProvider(): array
    {
        return [
            'empty body' => [[]],
            'missing sku' => [['quantity' => 1, 'destinationAddress' => '123 St', 'carrier' => 'UPS']],
            'missing quantity' => [['sku' => 'SKU-1', 'destinationAddress' => '123 St', 'carrier' => 'UPS']],
            'missing destinationAddress' => [['sku' => 'SKU-1', 'quantity' => 1, 'carrier' => 'UPS']],
            'missing carrier' => [['sku' => 'SKU-1', 'quantity' => 1, 'destinationAddress' => '123 St']],
        ];
    }

    public function testPurchaseLabelClientExceptionReturns400WithType(): void
    {
        $payload = [
            'sku' => 'SKU-TEST-1',
            'quantity' => 2,
            'destinationAddress' => '123 Test St',
            'carrier' => 'UPS',
        ];
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'system';

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new InvalidArgumentException('Invalid carrier requested'));

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid carrier requested', $content['error']);
        $this->assertEquals('InvalidArgumentException', $content['type']);
    }

    public function testPurchaseLabelDomainExceptionReturns400WithType(): void
    {
        $payload = [
            'sku' => 'SKU-TEST-1',
            'quantity' => 2,
            'destinationAddress' => '123 Test St',
            'carrier' => 'UPS',
        ];
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'system';

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new DomainException('Insufficient inventory balance'));

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Insufficient inventory balance', $content['error']);
        $this->assertEquals('DomainException', $content['type']);
    }

    public function testPurchaseLabelUnhandledExceptionReturns500(): void
    {
        $payload = [
            'sku' => 'SKU-TEST-1',
            'quantity' => 2,
            'destinationAddress' => '123 Test St',
            'carrier' => 'UPS',
        ];
        ShippingPhpStreamWrapper::$data = json_encode($payload);
        $_SERVER['auth.tenant_id'] = 'system';

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception('Database connection lost'));

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
