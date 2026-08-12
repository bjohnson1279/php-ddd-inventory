<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Infrastructure\Http\Response;
use InvalidArgumentException;
use Exception;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;

class PurchaseOrderControllerTest extends TestCase
{
    private PurchaseOrderController $controller;

    protected function setUp(): void
    {
        $this->controller = new PurchaseOrderController();
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
