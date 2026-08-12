<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\PurchaseOrderController;
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

class PurchaseOrderControllerTest extends TestCase
{
    private PurchaseOrderController $controller;
    private $requestMock;
    private $poRepoMock;
    private $productRepoMock;
    private $costLayerRepoMock;
    private $eventsMock;

    protected function setUp(): void
    {
        $this->controller = new PurchaseOrderController();
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
        );

        $this->poRepoMock->expects($this->once())
            ->method('findById')
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
    }
}
