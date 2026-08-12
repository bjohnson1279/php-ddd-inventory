<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
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

class PurchaseOrderControllerTest extends TestCase
{
    private PurchaseOrderController $controller;
    private $poRepoMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->controller = new PurchaseOrderController();
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
<<<<<<< HEAD
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
=======

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
>>>>>>> origin/master
    }
}
