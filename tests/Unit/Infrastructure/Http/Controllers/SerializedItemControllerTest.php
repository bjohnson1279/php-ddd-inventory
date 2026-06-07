<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\SerializedItemController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Serial\Services\SerializedInventoryService;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Infrastructure\Http\Response;
use Exception;

class SerializedItemControllerTest extends TestCase
{
    private $controller;

    protected function setUp(): void
    {
        $this->controller = new SerializedItemController();
        // Set the auth.user_id to simulate a logged-in user
        $_SERVER['auth.user_id'] = 'user-123';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.user_id']);
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
            'variant-id-1',
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
