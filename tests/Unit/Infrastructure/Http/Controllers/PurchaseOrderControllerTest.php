<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use Exception;
use InvalidArgumentException;

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
        );

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn($po);

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

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willReturn(null);

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

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
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

        $this->poRepoMock->expects($this->once())
            ->method('findById')
            ->with($poId)
            ->willThrowException(new Exception('Database connection failed.'));

        // Output buffer used to suppress error_log during test
        ob_start();
        $response = $this->controller->get($this->requestMock, $poId, $this->poRepoMock);
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
