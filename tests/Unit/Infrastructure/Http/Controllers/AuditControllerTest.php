<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\AuditController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Inventory\Services\AuditProcessorService;
use InventoryApp\Infrastructure\Http\Response;

class AuditControllerTest extends TestCase
{
    private $controller;
    private $serviceMock;

    protected function setUp(): void
    {
        $this->serviceMock = $this->createMock(AuditProcessorService::class);
        $this->controller = new AuditController();

        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('service');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->serviceMock);
    }

    public function test_it_can_run_audit_successfully(): void
    {
        $tenantId = 'tenant-1';
        $summary = ['total_checked' => 10, 'discrepancies' => 0];

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willReturn($summary);

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->runAudit($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals($summary, $decoded);
    }

    public function test_it_returns_400_on_domain_exception(): void
    {
        $tenantId = 'tenant-1';

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willThrowException(new \DomainException('Audit failed'));

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->runAudit($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals('Audit failed', $decoded['error']);
    }

    public function test_it_returns_500_on_general_exception(): void
    {
        $tenantId = 'tenant-1';

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willThrowException(new \Exception('Database error'));

        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->runAudit($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $rawContent = $response->getContent();
        $this->assertIsString($rawContent);

        $decoded = json_decode($rawContent, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $this->assertEquals('An internal server error occurred.', $decoded['error']);
    }
}
