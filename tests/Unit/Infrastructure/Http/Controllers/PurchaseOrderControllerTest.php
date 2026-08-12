<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
