<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\NotificationController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Notification\Services\NotificationService;
use InventoryApp\Infrastructure\Http\Response;
use Exception;

class NotificationControllerTest extends TestCase
{
    private $controller;
    private $serviceMock;

    protected function setUp(): void
    {
        $this->serviceMock = $this->createMock(NotificationService::class);
        $this->controller = new NotificationController();

        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('service');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->serviceMock);
    }

    public function testReadReturns200WithStaticMessage(): void
    {
        $tenantId = 'tenant-1';
        $notificationId = 'notif-1';

        $this->serviceMock->expects($this->once())
            ->method('markAsRead')
            ->with($tenantId, $notificationId);

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->read($requestMock, $tenantId, $notificationId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals('Notification marked as read', $decoded['message']);
    }

    public function testReadReturns400OnException(): void
    {
        $tenantId = 'tenant-1';
        $notificationId = 'notif-1';

        $this->serviceMock->expects($this->once())
            ->method('markAsRead')
            ->with($tenantId, $notificationId)
            ->willThrowException(new Exception('Notification not found'));

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->read($requestMock, $tenantId, $notificationId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals('Notification not found', $decoded['error']);
    }
}