<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use DomainException;
use Exception;

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
        );

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn($po);

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

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn(null);

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

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
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

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willThrowException(new Exception('Unexpected database failure'));

        $this->poRepoMock->expects($this->never())->method('save');

        // Output buffering to hide the error_log from test output
        ob_start();
        $response = $this->controller->send($this->requestMock, $poId, $this->poRepoMock);
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }
}
