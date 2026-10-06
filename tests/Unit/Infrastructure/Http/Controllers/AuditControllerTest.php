<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\AuditController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Inventory\Services\AuditProcessorService;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentAuditDiscrepancyRepository;
use ReflectionClass;

class AuditControllerTest extends TestCase
{
    private AuditController $controller;
    private $serviceMock;
    private $repoMock;
    private ?string $originalAuthTenantId = null;

    protected function setUp(): void
    {
        $this->originalAuthTenantId = $_SERVER['auth.tenant_id'] ?? null;
        $this->controller = new AuditController();

        $this->serviceMock = $this->createMock(AuditProcessorService::class);
        $this->repoMock = $this->createMock(EloquentAuditDiscrepancyRepository::class);

        $reflection = new ReflectionClass($this->controller);

        $serviceProp = $reflection->getProperty('service');
        $serviceProp->setAccessible(true);
        $serviceProp->setValue($this->controller, $this->serviceMock);

        $repoProp = $reflection->getProperty('repo');
        $repoProp->setAccessible(true);
        $repoProp->setValue($this->controller, $this->repoMock);
    }

    protected function tearDown(): void
    {
        if ($this->originalAuthTenantId !== null) {
            $_SERVER['auth.tenant_id'] = $this->originalAuthTenantId;
        } else {
            unset($_SERVER['auth.tenant_id']);
        }
    }

    public function testRunAuditReturns403WhenTenantMismatch(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-a';
        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->runAudit($requestMock, 'tenant-b');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Unauthorized'], $decoded);
    }

    public function testListDiscrepanciesReturns403WhenTenantMismatch(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-a';
        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->listDiscrepancies($requestMock, 'tenant-b');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Unauthorized'], $decoded);
    }

    public function testResolveDiscrepancyReturns403WhenTenantMismatch(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-a';
        $requestMock = $this->createMock(RequestInterface::class);

        $response = $this->controller->resolveDiscrepancy($requestMock, 'tenant-b', 'disc-1');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Unauthorized'], $decoded);
    }

    public function testListDiscrepanciesReturns200WhenTenantMatches(): void
    {
        $_SERVER['auth.tenant_id'] = 'tenant-a';
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('status')
            ->willReturn(null);

        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->with('tenant-a', null)
            ->willReturn([]);

        $response = $this->controller->listDiscrepancies($requestMock, 'tenant-a');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals(['discrepancies' => []], $decoded);
    }
}
