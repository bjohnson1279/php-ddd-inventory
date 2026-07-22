<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\AuditController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Inventory\Services\AuditProcessorService;
use Exception;
use InvalidArgumentException;

class AuditControllerTest extends TestCase
{
    private AuditController $controller;
    private $auditProcessorServiceMock;

    protected function setUp(): void
    {
        $this->controller = new AuditController();

        // Use Reflection to mock the AuditProcessorService initialized in the constructor
        $this->auditProcessorServiceMock = $this->createMock(AuditProcessorService::class);

        $reflection = new \ReflectionClass(AuditController::class);
        $serviceProperty = $reflection->getProperty('service');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->controller, $this->auditProcessorServiceMock);
    }

    public function test_it_returns_400_when_validation_fails(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('The notes field is required.'));

        $this->auditProcessorServiceMock->expects($this->never())
            ->method('resolveDiscrepancy');

        // Act
        $response = $this->controller->resolveDiscrepancy($requestMock, 'tenant-123', 'disc-456');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('The notes field is required.', $content['error']);
    }

    public function test_it_returns_404_when_discrepancy_not_found_or_already_resolved(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn(['notes' => 'Resolution explanation']);

        $this->auditProcessorServiceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with('tenant-123', 'disc-456', 'Resolution explanation')
            ->willReturn(false);

        // Act
        $response = $this->controller->resolveDiscrepancy($requestMock, 'tenant-123', 'disc-456');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Discrepancy not found or already resolved', $content['error']);
    }

    public function test_it_returns_200_on_successful_resolution(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn(['notes' => 'Resolution explanation']);

        $this->auditProcessorServiceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with('tenant-123', 'disc-456', 'Resolution explanation')
            ->willReturn(true);

        // Act
        $response = $this->controller->resolveDiscrepancy($requestMock, 'tenant-123', 'disc-456');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $content);
        $this->assertTrue($content['success']);
    }

    public function test_it_returns_500_on_unexpected_internal_error(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn(['notes' => 'Resolution explanation']);

        $this->auditProcessorServiceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with('tenant-123', 'disc-456', 'Resolution explanation')
            ->willThrowException(new Exception('Database connection failed'));

        // Act
        $response = $this->controller->resolveDiscrepancy($requestMock, 'tenant-123', 'disc-456');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
