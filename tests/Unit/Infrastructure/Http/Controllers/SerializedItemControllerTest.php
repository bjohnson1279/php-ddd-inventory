<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\SerializedItemController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Serial\Services\SerializedInventoryService;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use Exception;
use Psr\Http\Message\StreamInterface;

class SerializedItemControllerTest extends TestCase
{
    private SerializedItemController $controller;
    private $serviceMock;
    private $repoMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->controller = new SerializedItemController();
        $serviceMock = $this->createMock(SerializedInventoryService::class);
        $repoMock = $this->createMock(SerializedItemRepositoryInterface::class);
        $requestMock = $this->createMock(RequestInterface::class);

        // Required to prevent undefined index error
        $_SERVER['auth.user_id'] = 'user-123';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.user_id']);
    }

    public function testReceiveSuccessReturns200(): void
    {
        $id = 'item-123';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'location_id' => 'loc-1',
                'purchase_order_id' => 'po-1',
            ]);

        $item = new SerializedItem($id, 'var-1', new SerialNumber('SN-123'), 'tenant-1', 'loc-unknown');

        $repoMock = $this->createMock(SerializedItemRepositoryInterface::class);
        $repoMock = $this->createMock(SerializedItemRepositoryInterface::class);
        $repoMock->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($item);

        $serviceMock = $this->createMock(SerializedInventoryService::class);
        $serviceMock = $this->createMock(SerializedInventoryService::class);
        $serviceMock->expects($this->once())
            ->method('receive')
            ->with(
                $this->callback(function (SerialNumber $sn) {
                    return $sn->value === 'SN-123';
                }),
                'tenant-1',
                'loc-1',
                'po-1',
                0,
                'user-123'
            );

        $response = $this->controller->receive($requestMock, $id, $serviceMock, $repoMock);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Serial item received', $response->getContent());
    }

    public function testReceiveItemNotFoundReturns404(): void
    {
        $id = 'non-existent-item';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'location_id' => 'loc-1',
                'purchase_order_id' => 'po-1',
            ]);

        $repoMock = $this->createMock(SerializedItemRepositoryInterface::class);
        $repoMock->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $serviceMock = $this->createMock(SerializedInventoryService::class);
        $serviceMock->expects($this->never())->method('receive');

        $response = $this->controller->receive($requestMock, $id, $serviceMock, $repoMock);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Serial item not found', $response->getContent());
    }

    public function testReceiveExceptionReturns400(): void
    {
        $id = 'item-123';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new Exception('Validation failed'));

        $serviceMock = $this->createMock(SerializedInventoryService::class);
        $repoMock = $this->createMock(SerializedItemRepositoryInterface::class);

        $response = $this->controller->receive($requestMock, $id, $serviceMock, $repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    public function testReceiveServiceExceptionReturns400(): void
    {
        $id = 'item-123';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'location_id' => 'loc-1',
                'purchase_order_id' => 'po-1',
            ]);

        $item = new SerializedItem($id, 'var-1', new SerialNumber('SN-123'), 'tenant-1', 'loc-unknown');

        $repoMock = $this->createMock(SerializedItemRepositoryInterface::class);
        $repoMock = $this->createMock(SerializedItemRepositoryInterface::class);
        $repoMock->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($item);

        $serviceMock = $this->createMock(SerializedInventoryService::class);
        $serviceMock = $this->createMock(SerializedInventoryService::class);
        $serviceMock->expects($this->once())
            ->method('receive')
            ->willThrowException(new Exception('Domain logic error'));

        $response = $this->controller->receive($requestMock, $id, $serviceMock, $repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Domain logic error', $response->getContent());
    }

    public function testReceiveReturns400OnException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('validate')->willReturn([
            'location_id' => 'loc-1',
            'purchase_order_id' => 'po-1'
        ]);

        $service = $this->createMock(SerializedInventoryService::class);
        $service->method('receive')->willThrowException(new Exception('Simulated Service Error'));

        $repo = $this->createMock(SerializedItemRepositoryInterface::class);

        $item = new SerializedItem(
            'item-id-1',
            'var-1',
            new SerialNumber('SN12345'),
            'tenant-1',
            'loc-0'
        );

        $repo->method('findById')->willReturn($item);

        $response = $this->controller->receive($request, 'item-id-1', $service, $repo);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Simulated Service Error', $response->getContent());
    }
}
