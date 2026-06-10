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

    public function testListReturns200WithNotifications(): void
    {
        $tenantId = 'tenant-1';
        $notifications = [
            ['id' => 'notif-1', 'message' => 'First notification'],
            ['id' => 'notif-2', 'message' => 'Second notification'],
        ];

        $this->serviceMock->expects($this->once())
            ->method('getNotifications')
            ->with($tenantId)
            ->willReturn($notifications);

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->list($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals($notifications, $decoded['notifications']);
    }

    public function testListReturns400OnException(): void
    {
        $tenantId = 'tenant-1';

        $this->serviceMock->expects($this->once())
            ->method('getNotifications')
            ->with($tenantId)
            ->willThrowException(new Exception('Error fetching notifications'));

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->list($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals('Error fetching notifications', $decoded['error']);
    }

    public function testReadAllReturns200WithStaticMessage(): void
    {
        $tenantId = 'tenant-1';

        $this->serviceMock->expects($this->once())
            ->method('markAllAsRead')
            ->with($tenantId);

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->readAll($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals('All notifications marked as read', $decoded['message']);
    }

    public function testReadAllReturns400OnException(): void
    {
        $tenantId = 'tenant-1';

        $this->serviceMock->expects($this->once())
            ->method('markAllAsRead')
            ->with($tenantId)
            ->willThrowException(new Exception('Error marking all as read'));

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->readAll($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals('Error marking all as read', $decoded['error']);
    }
}