<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
<<<<<<< HEAD
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Infrastructure\Http\Response;
use InvalidArgumentException;
use Exception;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
=======
<<<<<<< HEAD
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Infrastructure\Http\Response;

class PurchaseOrderControllerTest extends TestCase
{
    private $poRepo;
    private $controller;
    private $request;

    protected function setUp(): void
    {
        $this->poRepo = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->controller = new PurchaseOrderController();
        $this->request = $this->createMock(RequestInterface::class);
    }

    public function test_approve_returns_200_on_success(): void
    {
        $id = 'po-123';
        $poMock = $this->createMock(PurchaseOrder::class);

        $this->poRepo->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($poMock);

        $poMock->expects($this->once())
            ->method('approve');

        $this->poRepo->expects($this->once())
            ->method('save')
            ->with($poMock);

        $response = $this->controller->approve($this->request, $id, $this->poRepo);
=======
<<<<<<< HEAD
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Infrastructure\Http\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;
use InvalidArgumentException;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Inventory\Entities\Product;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
=======
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
<<<<<<< HEAD
use DomainException;
use Exception;
=======
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use Exception;
use InvalidArgumentException;
>>>>>>> origin/master
<<<<<<< HEAD
=======
>>>>>>> origin/master
>>>>>>> origin/master

class PurchaseOrderControllerTest extends TestCase
{
    private PurchaseOrderController $controller;
<<<<<<< HEAD
=======
<<<<<<< HEAD
    private $requestMock;
    private $poRepoMock;
    private $productRepoMock;
    private $costLayerRepoMock;
    private $eventsMock;
=======
    private $poRepoMock;
    private $requestMock;
>>>>>>> origin/master
>>>>>>> origin/master

    protected function setUp(): void
    {
        $this->controller = new PurchaseOrderController();
<<<<<<< HEAD
    }

    public function test_create_returns_201_on_success(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'purchaseOrderNumber' => 'PO-123',
                'vendorId'            => 'V-1',
                'tenantId'            => 'T-1',
                'locationId'          => 'L-1',
                'items'               => [
                    [
                        'variantId'     => 'VAR-1',
                        'quantity'      => 10,
                        'unitCostCents' => 1000
                    ]
                ]
            ]);

        $poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $poRepoMock->expects($this->once())
            ->method('findByNumber')
            ->with('PO-123')
            ->willReturn(null);

        $poRepoMock->expects($this->once())
            ->method('save');

        $response = $this->controller->create($requestMock, $poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('PO-123', $content['purchaseOrderNumber']);
        $this->assertEquals('V-1', $content['vendorId']);
        $this->assertEquals('T-1', $content['tenantId']);
        $this->assertEquals('L-1', $content['locationId']);
        $this->assertCount(1, $content['items']);
        $this->assertEquals('VAR-1', $content['items'][0]['variantId']);
    }

    public function test_create_returns_400_on_expected_exceptions(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('Validation failed.'));

        $poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);

        $response = $this->controller->create($requestMock, $poRepoMock);
=======
<<<<<<< HEAD
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $this->costLayerRepoMock = $this->createMock(CostLayerRepositoryInterface::class);
        $this->eventsMock = $this->createMock(EventDispatcherInterface::class);
    }

    public function testReceiveSuccess(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->with(['items' => 'required|array'])
            ->willReturn([
                'items' => [
                    ['variantId' => 'VARIANT-1', 'quantityReceived' => 5]
                ]
            ]);

        $item1 = new PurchaseOrderItem('item-1', 'VARIANT-1', 10, 500);
        $po = new PurchaseOrder(
            'po-123',
            'PO-NUM-001',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Sent,
            [$item1]
=======
        $this->poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
    }

<<<<<<< HEAD
    public function testSendReturns200OnSuccess(): void
    {
        $poId = 'po-123';

        $po = new PurchaseOrder(
            $poId,
            'PO-NUM-001',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Approved,
            []
=======
    public function testGetReturns200AndFormattedDataWhenPurchaseOrderExists(): void
    {
        $poId = 'po-123';

        $item = new PurchaseOrderItem('item-1', 'variant-1', 10, 1000, 5);
        $po = new PurchaseOrder(
            $poId,
            'PO-001',
            'vendor-1',
            'tenant-1',
            'loc-1',
            PurchaseOrderStatus::PartiallyReceived,
            [$item]
>>>>>>> origin/master
        );

        $this->poRepoMock->expects($this->once())
            ->method('findById')
<<<<<<< HEAD
            ->with('po-123')
            ->willReturn($po);

        $productMock = Product::create(
            'prod-1',
            new SKU('VARIANT-1'),
            'Product 1',
            new Department('DEP1'),
            new LocationId('LOC-1'),
            new Quantity(0)
        );

        $this->productRepoMock->expects($this->once())
            ->method('findBySkus')
            ->willReturn(['VARIANT-1' => $productMock]);

        $this->productRepoMock->expects($this->once())
            ->method('saveAll');

        $this->costLayerRepoMock->expects($this->once())
            ->method('saveBatch');

        $this->poRepoMock->expects($this->once())
            ->method('save');

        $response = $this->controller->receive(
            $this->requestMock,
            'po-123',
            $this->poRepoMock,
            $this->productRepoMock,
            $this->costLayerRepoMock,
            $this->eventsMock
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Items received successfully', $response->getContent());
    }

    public function testReceiveValidationFailure(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('Validation failed: items is required'));

        $response = $this->controller->receive(
            $this->requestMock,
            'po-123',
            $this->poRepoMock,
            $this->productRepoMock,
            $this->costLayerRepoMock,
            $this->eventsMock
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    public function testReceiveInternalError(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'items' => [
                    ['variantId' => 'VARIANT-1', 'quantityReceived' => 5]
                ]
            ]);

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->willThrowException(new Exception('Database connection lost'));

        $response = $this->controller->receive(
            $this->requestMock,
            'po-123',
            $this->poRepoMock,
            $this->productRepoMock,
            $this->costLayerRepoMock,
            $this->eventsMock
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
=======
            ->with($poId)
            ->willReturn($po);

<<<<<<< HEAD
        $this->poRepoMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PurchaseOrder $savedPo) {
                return $savedPo->getStatus() === PurchaseOrderStatus::Sent;
            }));

        $response = $this->controller->send($this->requestMock, $poId, $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Purchase order sent to vendor successfully', $response->getContent());
    }

    public function testSendReturns404WhenPurchaseOrderNotFound(): void
    {
        $poId = 'po-123';
=======
        $response = $this->controller->get($this->requestMock, $poId, $this->poRepoMock);
>>>>>>> origin/master

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
<<<<<<< HEAD
        $this->assertEquals('Purchase order approved successfully', $content['message']);
    }

    public function test_approve_returns_404_when_po_not_found(): void
    {
        $id = 'po-unknown';

        $this->poRepo->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $response = $this->controller->approve($this->request, $id, $this->poRepo);
=======

        $this->assertEquals($poId, $content['id']);
        $this->assertEquals('PO-001', $content['purchaseOrderNumber']);
        $this->assertEquals(PurchaseOrderStatus::PartiallyReceived->value, $content['status']);
        $this->assertEquals('vendor-1', $content['vendorId']);
        $this->assertEquals('tenant-1', $content['tenantId']);
        $this->assertEquals('loc-1', $content['locationId']);

        $this->assertIsArray($content['items']);
        $this->assertCount(1, $content['items']);
        $this->assertEquals('item-1', $content['items'][0]['id']);
        $this->assertEquals('variant-1', $content['items'][0]['variantId']);
        $this->assertEquals(10, $content['items'][0]['quantity']);
        $this->assertEquals(5, $content['items'][0]['receivedQuantity']);
        $this->assertEquals(1000, $content['items'][0]['unitCostCents']);
    }

    public function testGetReturns404WhenPurchaseOrderNotFound(): void
    {
        $poId = 'nonexistent-po';
>>>>>>> origin/master

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn(null);

<<<<<<< HEAD
        $this->poRepoMock->expects($this->never())->method('save');

        $response = $this->controller->send($this->requestMock, $poId, $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Purchase order not found', $response->getContent());
    }

    public function testSendReturns400WhenDomainExceptionThrown(): void
    {
        $poId = 'po-123';

        $po = new PurchaseOrder(
            $poId,
            'PO-NUM-001',
            'vendor-1',
            'tenant-1',
            'LOC-1',
            PurchaseOrderStatus::Draft, // Invalid state for send
            []
        );
=======
        $response = $this->controller->get($this->requestMock, $poId, $this->poRepoMock);
>>>>>>> origin/master

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
<<<<<<< HEAD
        $this->assertEquals('Purchase order not found', $content['error']);
    }

    public function test_approve_returns_400_on_domain_exception(): void
    {
        $id = 'po-123';
        $poMock = $this->createMock(PurchaseOrder::class);

        $this->poRepo->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($poMock);

        $poMock->expects($this->once())
            ->method('approve')
            ->willThrowException(new \DomainException('Only draft purchase orders can be approved.'));

        $this->poRepo->expects($this->never())
            ->method('save');

        $response = $this->controller->approve($this->request, $id, $this->poRepo);
=======
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Purchase order not found', $content['error']);
    }

    public function testGetReturns400OnDomainException(): void
    {
        $poId = 'po-error';
        $exceptionMessage = 'Invalid argument provided.';
>>>>>>> origin/master

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
<<<<<<< HEAD
            ->willReturn($po);

        $this->poRepoMock->expects($this->never())->method('save');

        $response = $this->controller->send($this->requestMock, $poId, $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Only approved purchase orders can be sent', $response->getContent());
    }

    public function testSendReturns500WhenUnexpectedExceptionThrown(): void
    {
        $poId = 'po-123';
=======
            ->willThrowException(new InvalidArgumentException($exceptionMessage));

        $response = $this->controller->get($this->requestMock, $poId, $this->poRepoMock);
>>>>>>> origin/master
>>>>>>> origin/master

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
<<<<<<< HEAD
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Validation failed.', $content['error']);
    }

    public function test_create_returns_500_on_internal_server_error(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'purchaseOrderNumber' => 'PO-123',
                'vendorId'            => 'V-1',
                'tenantId'            => 'T-1',
                'locationId'          => 'L-1',
                'items'               => []
            ]);

        $poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $poRepoMock->expects($this->once())
            ->method('findByNumber')
            ->willThrowException(new Exception('Database error.'));

        $response = $this->controller->create($requestMock, $poRepoMock);
=======
<<<<<<< HEAD
        $this->assertEquals('Only draft purchase orders can be approved.', $content['error']);
    }

    public function test_approve_returns_500_on_internal_server_error(): void
    {
        $id = 'po-123';

        $this->poRepo->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willThrowException(new \Exception('Database connection failed.'));

        $response = $this->controller->approve($this->request, $id, $this->poRepo);
=======
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals($exceptionMessage, $content['error']);
    }

    public function testGetReturns500OnGenericException(): void
    {
        $poId = 'po-error';
>>>>>>> origin/master

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
<<<<<<< HEAD
            ->willThrowException(new Exception('Unexpected database failure'));

        $this->poRepoMock->expects($this->never())->method('save');

        // Output buffering to hide the error_log from test output
        ob_start();
        $response = $this->controller->send($this->requestMock, $poId, $this->poRepoMock);
=======
            ->willThrowException(new Exception('Database connection failed.'));

        // Output buffer used to suppress error_log during test
        ob_start();
        $response = $this->controller->get($this->requestMock, $poId, $this->poRepoMock);
>>>>>>> origin/master
        ob_end_clean();
>>>>>>> origin/master
>>>>>>> origin/master

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
<<<<<<< HEAD
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
=======

        $content = json_decode($response->getContent(), true);
<<<<<<< HEAD
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
<<<<<<< HEAD
=======
=======
<<<<<<< HEAD
        $this->assertEquals('An internal server error occurred.', $content['error']);
=======
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
>>>>>>> origin/master
>>>>>>> origin/master
>>>>>>> origin/master
>>>>>>> origin/master
    }
}
