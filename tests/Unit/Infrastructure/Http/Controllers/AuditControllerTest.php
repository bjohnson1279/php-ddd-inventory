<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\AuditController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Inventory\Services\AuditProcessorService;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentAuditDiscrepancyRepository;
use InventoryApp\Domain\Inventory\Entities\AuditDiscrepancy;
use ReflectionClass;
use Exception;
use InvalidArgumentException;
use DomainException;

class AuditControllerTest extends TestCase
{
    private AuditController $controller;
    private $serviceMock;
    private $repoMock;

    protected function setUp(): void
    {
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

    public function testResolveDiscrepancyReturns200OnSuccess(): void
    {
        $tenantId = 'tenant-123';
        $discrepancyId = 'disc-456';
        $notes = 'Resolved discrepancy by updating ledger';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with(['notes' => 'required|string'])
            ->willReturn(['notes' => $notes]);

        $this->serviceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with($tenantId, $discrepancyId, $notes)
            ->willReturn(true);

        $response = $this->controller->resolveDiscrepancy($requestMock, $tenantId, $discrepancyId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['success' => true], $content);
    }

    public function testResolveDiscrepancyReturns404WhenNotFoundOrAlreadyResolved(): void
    {
        $tenantId = 'tenant-123';
        $discrepancyId = 'disc-999';
        $notes = 'Closing out';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with(['notes' => 'required|string'])
            ->willReturn(['notes' => $notes]);

        $this->serviceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with($tenantId, $discrepancyId, $notes)
            ->willReturn(false);

        $response = $this->controller->resolveDiscrepancy($requestMock, $tenantId, $discrepancyId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Discrepancy not found or already resolved'], $content);
    }

    public function testResolveDiscrepancyReturns400OnValidationOrDomainException(): void
    {
        $tenantId = 'tenant-123';
        $discrepancyId = 'disc-456';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('The notes field is required.'));

        $this->serviceMock->expects($this->never())
            ->method('resolveDiscrepancy');

        $response = $this->controller->resolveDiscrepancy($requestMock, $tenantId, $discrepancyId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'The notes field is required.'], $content);
    }

    public function testResolveDiscrepancyReturns400OnDomainExceptionFromService(): void
    {
        $tenantId = 'tenant-123';
        $discrepancyId = 'disc-456';
        $notes = 'Notes';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn(['notes' => $notes]);

        $this->serviceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with($tenantId, $discrepancyId, $notes)
            ->willThrowException(new DomainException('Invalid discrepancy state'));

        $response = $this->controller->resolveDiscrepancy($requestMock, $tenantId, $discrepancyId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Invalid discrepancy state'], $content);
    }

    public function testResolveDiscrepancyReturns500OnUnexpectedException(): void
    {
        $tenantId = 'tenant-123';
        $discrepancyId = 'disc-456';
        $notes = 'Notes';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn(['notes' => $notes]);

        $this->serviceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with($tenantId, $discrepancyId, $notes)
            ->willThrowException(new Exception('Database connection dropped'));

        $response = $this->controller->resolveDiscrepancy($requestMock, $tenantId, $discrepancyId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'An internal server error occurred.'], $content);
    }

    public function testRunAuditReturns200WithSummary(): void
    {
        $tenantId = 'tenant-123';
        $summary = [
            'shopifyDiscrepancies' => 2,
            'accountingDiscrepancies' => 1,
        ];

        $requestMock = $this->createMock(RequestInterface::class);

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willReturn($summary);

        $response = $this->controller->runAudit($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($summary, $content);
    }

    public function testRunAuditReturns400OnDomainException(): void
    {
        $tenantId = 'tenant-123';
        $requestMock = $this->createMock(RequestInterface::class);

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willThrowException(new DomainException('Tenant not active'));

        $response = $this->controller->runAudit($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Tenant not active'], $content);
    }

    public function testRunAuditReturns500OnUnexpectedException(): void
    {
        $tenantId = 'tenant-123';
        $requestMock = $this->createMock(RequestInterface::class);

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willThrowException(new Exception('Network timeout'));

        $response = $this->controller->runAudit($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'An internal server error occurred.'], $content);
    }

    public function testListDiscrepanciesReturns200WithFormattedDiscrepancies(): void
    {
        $tenantId = 'tenant-123';
        $status = 'OPEN';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('status')
            ->willReturn($status);

        $occurredAt = new \DateTimeImmutable('2026-03-01T10:00:00+00:00');
        $resolvedAt = new \DateTimeImmutable('2026-03-01T12:00:00+00:00');

        $discrepancy = new AuditDiscrepancy(
            id: 'disc-1',
            tenantId: $tenantId,
            type: 'SHOPIFY_STOCK_MISMATCH',
            referenceId: 'SKU-1:LOC-1',
            externalRefId: 'ext-1',
            description: 'Mismatch description',
            status: 'RESOLVED',
            occurredAt: $occurredAt,
            resolvedAt: $resolvedAt,
            resolutionNotes: 'Fixed'
        );

        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->with($tenantId, $status)
            ->willReturn([$discrepancy]);

        $response = $this->controller->listDiscrepancies($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('discrepancies', $content);
        $this->assertCount(1, $content['discrepancies']);
        $this->assertEquals([
            'id' => 'disc-1',
            'tenantId' => $tenantId,
            'type' => 'SHOPIFY_STOCK_MISMATCH',
            'referenceId' => 'SKU-1:LOC-1',
            'externalRefId' => 'ext-1',
            'description' => 'Mismatch description',
            'status' => 'RESOLVED',
            'occurredAt' => $occurredAt->format(\DateTimeInterface::ATOM),
            'resolvedAt' => $resolvedAt->format(\DateTimeInterface::ATOM),
            'resolutionNotes' => 'Fixed',
        ], $content['discrepancies'][0]);
    }

    public function testListDiscrepanciesReturns400OnDomainException(): void
    {
        $tenantId = 'tenant-123';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('status')
            ->willReturn('INVALID');

        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->with($tenantId, 'INVALID')
            ->willThrowException(new InvalidArgumentException('Invalid status'));

        $response = $this->controller->listDiscrepancies($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'Invalid status'], $content);
    }

    public function testListDiscrepanciesReturns500OnUnexpectedException(): void
    {
        $tenantId = 'tenant-123';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('status')
            ->willReturn(null);

        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->with($tenantId, null)
            ->willThrowException(new Exception('Database query failed'));

        $response = $this->controller->listDiscrepancies($requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => 'An internal server error occurred.'], $content);
    }
}
