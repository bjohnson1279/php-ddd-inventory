<?php

namespace InventoryApp\Infrastructure\Http\Controllers {
    if (!function_exists('InventoryApp\Infrastructure\Http\Controllers\file_get_contents')) {
        function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $length = null) {
            if ($filename === 'php://input' && isset($GLOBALS['__mock_php_input'])) {
                return $GLOBALS['__mock_php_input'];
            }
            return \file_get_contents($filename, $use_include_path, $context, $offset, $length);
        }
    }
}

namespace Tests\Unit\Infrastructure\Http\Controllers {

    use PHPUnit\Framework\TestCase;
    use InventoryApp\Infrastructure\Http\Controllers\ShippingController;
    use InventoryApp\Infrastructure\Http\RequestInterface;
    use InventoryApp\Infrastructure\Http\Response;
    use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
    use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabelResult;

    class ShippingControllerTest extends TestCase
    {
        private ShippingController $controller;
        private $requestMock;
        private $useCaseMock;
        private ?string $prevAuthTenant;

        protected function setUp(): void
        {
            $this->controller = new ShippingController();
            $this->requestMock = $this->createMock(RequestInterface::class);
            $this->useCaseMock = $this->createMock(PurchaseShippingLabel::class);
            $this->prevAuthTenant = $_SERVER['auth.tenant_id'] ?? null;
            unset($GLOBALS['__mock_php_input']);
        }

        protected function tearDown(): void
        {
            if ($this->prevAuthTenant !== null) {
                $_SERVER['auth.tenant_id'] = $this->prevAuthTenant;
            } else {
                unset($_SERVER['auth.tenant_id']);
            }
            unset($GLOBALS['__mock_php_input']);
        }

        public function testPurchaseLabelReturns403WhenUnauthenticated(): void
        {
            unset($_SERVER['auth.tenant_id']);

            $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(403, $response->getStatusCode());
            $body = json_decode($response->getContent(), true);
            $this->assertEquals('Unauthorized', $body['error']);
        }

        public function testPurchaseLabelReturns403WhenTenantMismatch(): void
        {
            $_SERVER['auth.tenant_id'] = 'tenant-1';
            $GLOBALS['__mock_php_input'] = json_encode([
                'sku' => 'SKU-1',
                'quantity' => 2,
                'destinationAddress' => '123 Main St',
                'carrier' => 'UPS Ground',
                'tenantId' => 'tenant-2'
            ]);

            $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(403, $response->getStatusCode());
            $body = json_decode($response->getContent(), true);
            $this->assertEquals('Unauthorized', $body['error']);
        }

        public function testPurchaseLabelSucceedsForMatchingTenant(): void
        {
            $_SERVER['auth.tenant_id'] = 'tenant-1';
            $GLOBALS['__mock_php_input'] = json_encode([
                'sku' => 'SKU-1',
                'quantity' => 2,
                'destinationAddress' => '123 Main St',
                'carrier' => 'UPS Ground',
                'tenantId' => 'tenant-1',
                'locationId' => 'LOC-1'
            ]);

            $resultMock = new PurchaseShippingLabelResult(
                shipmentId: 'ship-123',
                trackingNumber: '1Z999',
                labelUrl: 'https://example.com/label.pdf',
                rateCents: 1500
            );

            $this->useCaseMock->expects($this->once())
                ->method('execute')
                ->with('SKU-1', 2, '123 Main St', 'UPS Ground', 'LOC-1', 'tenant-1')
                ->willReturn($resultMock);

            $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(201, $response->getStatusCode());
            $body = json_decode($response->getContent(), true);
            $this->assertEquals('ship-123', $body['shipmentId']);
            $this->assertEquals('Shipping label purchased successfully.', $body['message']);
        }

        public function testPurchaseLabelAllowsSystemToPurchaseForAnyTenant(): void
        {
            $_SERVER['auth.tenant_id'] = 'system';
            $GLOBALS['__mock_php_input'] = json_encode([
                'sku' => 'SKU-1',
                'quantity' => 2,
                'destinationAddress' => '123 Main St',
                'carrier' => 'UPS Ground',
                'tenantId' => 'tenant-99',
                'locationId' => 'LOC-1'
            ]);

            $resultMock = new PurchaseShippingLabelResult(
                shipmentId: 'ship-456',
                trackingNumber: '1Z888',
                labelUrl: 'https://example.com/label2.pdf',
                rateCents: 2000
            );

            $this->useCaseMock->expects($this->once())
                ->method('execute')
                ->with('SKU-1', 2, '123 Main St', 'UPS Ground', 'LOC-1', 'tenant-99')
                ->willReturn($resultMock);

            $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(201, $response->getStatusCode());
        }
    }
}
