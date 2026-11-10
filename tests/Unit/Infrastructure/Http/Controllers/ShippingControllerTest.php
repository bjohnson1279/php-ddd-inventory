<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;

class MockPhpStreamShipping
{
    public $context;
    private int $position = 0;
    public static string $data = '';

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string
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

class ShippingControllerTest extends TestCase
{
    private ShippingController $controller;
    private $useCaseMock;
    private $requestMock;
    private ?string $originalAuthTenantId = null;

    protected function setUp(): void
    {
        $this->controller = new ShippingController();
        $this->useCaseMock = $this->createMock(PurchaseShippingLabel::class);
        $this->requestMock = $this->createMock(RequestInterface::class);

        $this->originalAuthTenantId = $_SERVER['auth.tenant_id'] ?? null;

        if (in_array('php', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_register('php', MockPhpStreamShipping::class);

        MockPhpStreamShipping::$data = '';
    }

    protected function tearDown(): void
    {
        if (in_array('php', stream_get_wrappers(), true)) {
            stream_wrapper_restore('php');
        }

        if ($this->originalAuthTenantId !== null) {
            $_SERVER['auth.tenant_id'] = $this->originalAuthTenantId;
        } else {
            unset($_SERVER['auth.tenant_id']);
        }

        MockPhpStreamShipping::$data = '';
    }

    public function testPurchaseLabelUnauthorizedWhenTenantIdMismatches(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-a';

        MockPhpStreamShipping::$data = json_encode([
            'sku' => 'SKU-001',
            'quantity' => 2,
            'destinationAddress' => '123 Main St',
            'carrier' => 'FedEx',
            'tenantId' => 'tenant-b',
        ]);

        $this->useCaseMock->expects($this->never())->method('execute');

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['error' => 'Unauthorized'], json_decode($response->getContent(), true));
    }

    public function testPurchaseLabelUnauthorizedWhenAuthTenantIdIsNull(): void
    {
        unset($_SERVER['auth.tenant_id']);

        MockPhpStreamShipping::$data = json_encode([
            'sku' => 'SKU-001',
            'quantity' => 2,
            'destinationAddress' => '123 Main St',
            'carrier' => 'FedEx',
            'tenantId' => 'tenant-b',
        ]);

        $this->useCaseMock->expects($this->never())->method('execute');

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['error' => 'Unauthorized'], json_decode($response->getContent(), true));
    }

    public function testPurchaseLabelAuthorizedWhenAuthTenantIdIsSystem(): void
    {
        $_SERVER['auth.tenant_id'] = 'system';

        MockPhpStreamShipping::$data = json_encode([
            'sku' => 'SKU-001',
            'quantity' => 2,
            'destinationAddress' => '123 Main St',
            'carrier' => 'FedEx',
            'tenantId' => 'tenant-b',
        ]);

        $result = new PurchaseShippingLabelResult(
            'ship-1',
            'TRK-123',
            'https://labels.example.com/123.pdf',
            1500
        );

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->with('SKU-001', 2, '123 Main St', 'FedEx', 'default', 'tenant-b')
            ->willReturn($result);

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Shipping label purchased successfully.', $body['message']);
        $this->assertSame('ship-1', $body['shipmentId']);
        $this->assertSame('TRK-123', $body['trackingNumber']);
    }

    public function testPurchaseLabelAuthorizedWhenTenantMatches(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-a';

        MockPhpStreamShipping::$data = json_encode([
            'sku' => 'SKU-001',
            'quantity' => 2,
            'destinationAddress' => '123 Main St',
            'carrier' => 'FedEx',
            'tenantId' => 'tenant-a',
        ]);

        $result = new PurchaseShippingLabelResult(
            'ship-2',
            'TRK-456',
            'https://labels.example.com/456.pdf',
            1500
        );

        $this->useCaseMock->expects($this->once())
            ->method('execute')
            ->with('SKU-001', 2, '123 Main St', 'FedEx', 'default', 'tenant-a')
            ->willReturn($result);

        $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Shipping label purchased successfully.', $body['message']);
        $this->assertSame('ship-2', $body['shipmentId']);
        $this->assertSame('TRK-456', $body['trackingNumber']);
    }
}
