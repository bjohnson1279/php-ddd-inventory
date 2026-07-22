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
            ->willReturn(['notes' => 'Resolution explanation']);

        $this->auditProcessorServiceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with('tenant-123', 'disc-456', 'Resolution explanation')
            ->willReturn(false);


        $this->assertEquals(404, $response->getStatusCode());

        $this->assertEquals('Discrepancy not found or already resolved', $content['error']);
    }

    public function test_it_returns_200_on_successful_resolution(): void
    {

            ->willReturn(true);


        $this->assertEquals(200, $response->getStatusCode());

        $this->assertArrayHasKey('success', $content);
        $this->assertTrue($content['success']);
    }

    public function test_it_returns_500_on_unexpected_internal_error(): void
    {

            ->willThrowException(new Exception('Database connection failed'));


        $this->assertEquals(500, $response->getStatusCode());

        $this->assertEquals('An internal server error occurred.', $content['error']);

{
    private $controller;
    private $serviceMock;

    {
        $this->serviceMock = $this->createMock(AuditProcessorService::class);

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


        $response = $this->controller->runAudit($requestMock, $tenantId);


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

            ->willThrowException(new \DomainException('Audit failed'));





        }

        $this->assertEquals('Audit failed', $decoded['error']);
    }

    public function test_it_returns_500_on_general_exception(): void
    {

            ->willThrowException(new \Exception('Database error'));





        }

        $this->assertEquals('An internal server error occurred.', $decoded['error']);
    }
}
