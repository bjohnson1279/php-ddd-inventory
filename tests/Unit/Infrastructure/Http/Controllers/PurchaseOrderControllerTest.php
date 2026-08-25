<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;
use InvalidArgumentException;

class PurchaseOrderControllerTest extends TestCase
{
    private PurchaseOrderController $controller;
    private $requestMock;
    private $poRepoMock;

    protected function setUp(): void
    {
        $this->controller = new PurchaseOrderController();
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
    }

    public function test_create_returns_201_on_success(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'purchaseOrderNumber' => 'PO-123',
                'vendorId'            => 'v-1',
                'tenantId'            => 't-1',
                'locationId'          => 'loc-1',
                'items'               => [
                    ['id' => 'item-1', 'variantId' => 'var-1', 'quantity' => 10, 'unitCostCents' => 500]
                ]
            ]);

        $response = $this->controller->create($this->requestMock, $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_get_returns_200_on_success(): void
    {
        $po = new PurchaseOrder('po-123', 'PO-123', 'v-1', 't-1', 'loc-1', PurchaseOrderStatus::Draft);
        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        $response = $this->controller->get($this->requestMock, 'po-123', $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_get_returns_404_if_not_found(): void
    {
        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with('po-999')
            ->willReturn(null);

        $response = $this->controller->get($this->requestMock, 'po-999', $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_approve_returns_200_on_success(): void
    {
        $po = new PurchaseOrder('po-123', 'PO-123', 'v-1', 't-1', 'loc-1', PurchaseOrderStatus::Draft);
        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        $this->poRepoMock->expects($this->once())
            ->method('save')
            ->with($po);

        $response = $this->controller->approve($this->requestMock, 'po-123', $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(PurchaseOrderStatus::Approved, $po->getStatus());
    }

    public function test_approve_returns_404_if_not_found(): void
    {
        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with('po-999')
            ->willReturn(null);

        $response = $this->controller->approve($this->requestMock, 'po-999', $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_send_returns_200_on_success(): void
    {
        $po = new PurchaseOrder('po-123', 'PO-123', 'v-1', 't-1', 'loc-1', PurchaseOrderStatus::Approved);
        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        $this->poRepoMock->expects($this->once())
            ->method('save')
            ->with($po);

        $response = $this->controller->send($this->requestMock, 'po-123', $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(PurchaseOrderStatus::Sent, $po->getStatus());
    }

    public function test_send_returns_404_if_not_found(): void
    {
        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with('po-999')
            ->willReturn(null);

        $response = $this->controller->send($this->requestMock, 'po-999', $this->poRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_receive_returns_200_on_success(): void
    {
        $productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $costLayerRepoMock = $this->createMock(CostLayerRepositoryInterface::class);
        $eventsMock = $this->createMock(EventDispatcherInterface::class);

        $productMock = $this->createMock(\InventoryApp\Domain\Inventory\Entities\Product::class);
        $productMock->method('getId')->willReturn('prod-1');
        $productMock->method('releaseEvents')->willReturn([]);
        $productRepoMock->method('findBySkus')
            ->willReturn(['var-1' => $productMock]);

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'items' => [
                    ['variantId' => 'var-1', 'quantityReceived' => 5]
                ]
            ]);

        $po = new PurchaseOrder('po-123', 'PO-123', 'v-1', 't-1', 'LOC-1', PurchaseOrderStatus::Sent);
        $po->addItem(new PurchaseOrderItem('item-1', 'var-1', 10, 500, 0));

        $this->poRepoMock->expects($this->atLeastOnce())
            ->method('findById')
            ->with('po-123')
            ->willReturn($po);

        $response = $this->controller->receive(
            $this->requestMock,
            'po-123',
            $this->poRepoMock,
            $productRepoMock,
            $costLayerRepoMock,
            $eventsMock
        );

        $this->assertEquals('{"message":"Items received successfully"}', $response->getContent());
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetReturns500OnGenericException(): void
    {
        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with('po-error')
            ->willThrowException(new Exception('Database connection failed.'));

        ob_start();
        $response = $this->controller->get($this->requestMock, 'po-error', $this->poRepoMock);
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
