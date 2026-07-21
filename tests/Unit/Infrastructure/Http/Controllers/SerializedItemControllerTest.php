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
        $this->serviceMock = $this->createMock(SerializedInventoryService::class);
        $this->repoMock = $this->createMock(SerializedItemRepositoryInterface::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        // Set the auth.user_id to simulate a logged-in user
        $_SERVER['auth.user_id'] = 'user-123';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.user_id']);
    }

    public function testReceiveSuccessReturns200(): void
    {
        $id = 'item-123';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'location_id' => 'loc-1',
                'purchase_order_id' => 'po-1',
            ]);

        $item = new SerializedItem($id, 'var-1', new SerialNumber('SN-123'), 'tenant-1', 'loc-unknown');

        $this->repoMock->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($item);

        $this->serviceMock->expects($this->once())
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

        $response = $this->controller->receive($this->requestMock, $id, $this->serviceMock, $this->repoMock);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Serial item received', $response->getContent());
    }

    public function testReceiveItemNotFoundReturns404(): void
    {
        $id = 'non-existent-item';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'location_id' => 'loc-1',
                'purchase_order_id' => 'po-1',
            ]);

        $this->repoMock->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $this->serviceMock->expects($this->never())->method('receive');

        $response = $this->controller->receive($this->requestMock, $id, $this->serviceMock, $this->repoMock);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Serial item not found', $response->getContent());
    }

    public function testReceiveExceptionReturns400(): void
    {
        $id = 'item-123';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \DomainException('Validation failed'));

        $response = $this->controller->receive($this->requestMock, $id, $this->serviceMock, $this->repoMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    public function testReceiveServiceExceptionReturns400(): void
    {
        $id = 'item-123';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'location_id' => 'loc-1',
                'purchase_order_id' => 'po-1',
            ]);

        $item = new SerializedItem($id, 'var-1', new SerialNumber('SN-123'), 'tenant-1', 'loc-unknown');

        $this->repoMock->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($item);

        $this->serviceMock->expects($this->once())
            ->method('receive')
            ->willThrowException(new \DomainException('Domain logic error'));

        $response = $this->controller->receive($this->requestMock, $id, $this->serviceMock, $this->repoMock);

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
        $service->method('receive')->willThrowException(new \DomainException('Simulated Service Error'));

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
