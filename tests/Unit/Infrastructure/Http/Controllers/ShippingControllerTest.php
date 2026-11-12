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
    use InventoryApp\Application\Shipping\UseCases\RouteOrder;
    use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
    use InventoryApp\Application\Ports\CarrierRate;
    use InventoryApp\Domain\Shipping\Strategies\FulfillmentPlan;
    use InventoryApp\Domain\Shipping\Strategies\FulfillmentAllocation;
    use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
    use InventoryApp\Domain\Shipping\Aggregates\Shipment;
    use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
    use DateTimeImmutable;
    use InvalidArgumentException;
    use DomainException;
    use RuntimeException;
    use Exception;

    class ShippingControllerTest extends TestCase
    {
        private ShippingController $controller;
        private $requestMock;
        private $useCaseMock;
        private $routeOrderMock;
        private $calculateShippingRatesMock;
        private $shipmentRepoMock;
        private ?string $prevAuthTenant;

        protected function setUp(): void
        {
            $this->controller = new ShippingController();
            $this->requestMock = $this->createMock(RequestInterface::class);
            $this->useCaseMock = $this->createMock(PurchaseShippingLabel::class);
            $this->routeOrderMock = $this->createMock(RouteOrder::class);
            $this->calculateShippingRatesMock = $this->createMock(CalculateShippingRates::class);
            $this->shipmentRepoMock = $this->createMock(ShipmentRepositoryInterface::class);
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
            parent::tearDown();
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

            $this->useCaseMock->expects($this->never())->method('execute');

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
            $this->assertEquals('1Z999', $body['trackingNumber']);
            $this->assertEquals('https://example.com/label.pdf', $body['labelUrl']);
            $this->assertEquals(1500, $body['rateCents']);
            $this->assertEquals('Shipping label purchased successfully.', $body['message']);
        }

        public function testPurchaseLabelUsesDefaultLocationAndServerTenantWhenOmitted(): void
        {
            $_SERVER['auth.tenant_id'] = 'system';
            $GLOBALS['__mock_php_input'] = json_encode([
                'sku' => 'SKU-TEST-1',
                'quantity' => 1,
                'destinationAddress' => '456 Main St',
                'carrier' => 'FedEx'
            ]);

            $result = new PurchaseShippingLabelResult('ship-2', 'TRACK-456', 'http://label2.url', 2000);

            $this->useCaseMock->expects($this->once())
                ->method('execute')
                ->with('SKU-TEST-1', 1, '456 Main St', 'FedEx', 'default', 'system')
                ->willReturn($result);

            $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

            $this->assertEquals(201, $response->getStatusCode());
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

        /**
         * @dataProvider missingRequiredParametersProvider
         */
        public function testPurchaseLabelMissingParametersReturns400(array $payload): void
        {
            $_SERVER['auth.tenant_id'] = 'system';
            $GLOBALS['__mock_php_input'] = json_encode($payload);

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
            $_SERVER['auth.tenant_id'] = 'system';
            $GLOBALS['__mock_php_input'] = json_encode([
                'sku' => 'SKU-TEST-1',
                'quantity' => 2,
                'destinationAddress' => '123 Test St',
                'carrier' => 'UPS',
            ]);

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
            $_SERVER['auth.tenant_id'] = 'system';
            $GLOBALS['__mock_php_input'] = json_encode([
                'sku' => 'SKU-TEST-1',
                'quantity' => 2,
                'destinationAddress' => '123 Test St',
                'carrier' => 'UPS',
            ]);

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
            $_SERVER['auth.tenant_id'] = 'system';
            $GLOBALS['__mock_php_input'] = json_encode([
                'sku' => 'SKU-TEST-1',
                'quantity' => 2,
                'destinationAddress' => '123 Test St',
                'carrier' => 'UPS',
            ]);

            $this->useCaseMock->expects($this->once())
                ->method('execute')
                ->willThrowException(new Exception('Database connection lost'));

            $response = $this->controller->purchaseLabel($this->requestMock, $this->useCaseMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(500, $response->getStatusCode());

            $content = json_decode($response->getContent(), true);
            $this->assertEquals('An internal server error occurred.', $content['error']);
        }

        public function testRouteOrderMissingRequiredFieldsReturns400(): void
        {
            $GLOBALS['__mock_php_input'] = json_encode([
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
            $GLOBALS['__mock_php_input'] = json_encode([
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
            $GLOBALS['__mock_php_input'] = json_encode([
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
            $GLOBALS['__mock_php_input'] = json_encode([
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
            $GLOBALS['__mock_php_input'] = json_encode([
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

        public function testGetShipmentsReturns200WithFormattedShipments(): void
        {
            $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
            $updatedAt = new DateTimeImmutable('2025-01-02 12:00:00');

            $shipment1 = new Shipment(
                id: 'SHIP-001',
                sku: 'SKU-ABC',
                quantity: 3,
                destinationAddress: '123 Main St, Springfield, OR',
                carrier: 'USPS',
                trackingNumber: 'TRACK-111',
                labelUrl: 'https://shipping.example.com/labels/111.pdf',
                shippingRateCents: 1250,
                status: ShipmentStatus::LabelGenerated,
                createdAt: $createdAt,
                updatedAt: $updatedAt
            );

            $shipment2 = new Shipment(
                id: 'SHIP-002',
                sku: 'SKU-XYZ',
                quantity: 1,
                destinationAddress: '456 Elm St, Metropolis, NY',
                carrier: 'FedEx',
                trackingNumber: null,
                labelUrl: null,
                shippingRateCents: 850,
                status: ShipmentStatus::InTransit,
                createdAt: $createdAt,
                updatedAt: null
            );

            $this->shipmentRepoMock->expects($this->once())
                ->method('findAll')
                ->willReturn([$shipment1, $shipment2]);

            $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(200, $response->getStatusCode());

            $decoded = json_decode($response->getContent(), true);
            $this->assertIsArray($decoded);
            $this->assertCount(2, $decoded);

            $this->assertEquals([
                'id' => 'SHIP-001',
                'sku' => 'SKU-ABC',
                'quantity' => 3,
                'destinationAddress' => '123 Main St, Springfield, OR',
                'carrier' => 'USPS',
                'trackingNumber' => 'TRACK-111',
                'labelUrl' => 'https://shipping.example.com/labels/111.pdf',
                'shippingRateCents' => 1250,
                'status' => ShipmentStatus::LabelGenerated->value,
                'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
                'updatedAt' => $updatedAt->format(\DateTimeInterface::ATOM),
            ], $decoded[0]);

            $this->assertEquals([
                'id' => 'SHIP-002',
                'sku' => 'SKU-XYZ',
                'quantity' => 1,
                'destinationAddress' => '456 Elm St, Metropolis, NY',
                'carrier' => 'FedEx',
                'trackingNumber' => null,
                'labelUrl' => null,
                'shippingRateCents' => 850,
                'status' => ShipmentStatus::InTransit->value,
                'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
                'updatedAt' => $createdAt->format(\DateTimeInterface::ATOM),
            ], $decoded[1]);
        }

        public function testGetShipmentsReturns200WithEmptyListWhenNoShipments(): void
        {
            $this->shipmentRepoMock->expects($this->once())
                ->method('findAll')
                ->willReturn([]);

            $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(200, $response->getStatusCode());

            $decoded = json_decode($response->getContent(), true);
            $this->assertSame([], $decoded);
        }

        public function testGetShipmentsReturns400OnDomainException(): void
        {
            $this->shipmentRepoMock->expects($this->once())
                ->method('findAll')
                ->willThrowException(new DomainException('Domain constraint violated'));

            $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(400, $response->getStatusCode());

            $decoded = json_decode($response->getContent(), true);
            $this->assertEquals(['error' => 'Domain constraint violated'], $decoded);
        }

        public function testGetShipmentsReturns400OnInvalidArgumentException(): void
        {
            $this->shipmentRepoMock->expects($this->once())
                ->method('findAll')
                ->willThrowException(new InvalidArgumentException('Invalid argument'));

            $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(400, $response->getStatusCode());

            $decoded = json_decode($response->getContent(), true);
            $this->assertEquals(['error' => 'Invalid argument'], $decoded);
        }

        public function testGetShipmentsReturns500OnGenericException(): void
        {
            $this->shipmentRepoMock->expects($this->once())
                ->method('findAll')
                ->willThrowException(new Exception('Database connection failed'));

            $response = $this->controller->getShipments($this->requestMock, $this->shipmentRepoMock);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(500, $response->getStatusCode());

            $decoded = json_decode($response->getContent(), true);
            $this->assertEquals(['error' => 'An internal server error occurred.'], $decoded);
        }
    }
}
