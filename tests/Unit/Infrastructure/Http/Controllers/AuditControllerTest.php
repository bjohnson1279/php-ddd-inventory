<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\AuditController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Inventory\Services\AuditProcessorService;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentAuditDiscrepancyRepository;
use InventoryApp\Domain\Inventory\Entities\AuditDiscrepancy;
use DomainException;
use InvalidArgumentException;
use Exception;

class AuditControllerTest extends TestCase
{
    private AuditController $controller;
    private $serviceMock;
    private $repoMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->controller = new AuditController();
        $this->serviceMock = $this->createMock(AuditProcessorService::class);
        $this->repoMock = $this->createMock(EloquentAuditDiscrepancyRepository::class);
        $this->requestMock = $this->createMock(RequestInterface::class);

        $reflection = new \ReflectionClass($this->controller);

        $serviceProp = $reflection->getProperty('service');
        $serviceProp->setAccessible(true);
        $serviceProp->setValue($this->controller, $this->serviceMock);

        $repoProp = $reflection->getProperty('repo');
        $repoProp->setAccessible(true);
        $repoProp->setValue($this->controller, $this->repoMock);
    }

    public function testRunAuditReturns200OnSuccess(): void
    {
        $tenantId = 'tenant-123';
        $summary = [
            'shopifyDiscrepancies' => 2,
            'accountingDiscrepancies' => 1,
        ];

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willReturn($summary);

        $response = $this->controller->runAudit($this->requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals($summary, $decoded);
    }

    public function testRunAuditReturns400OnDomainException(): void
    {
        $tenantId = 'tenant-123';

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willThrowException(new DomainException('Audit domain validation failed'));

        $response = $this->controller->runAudit($this->requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['error' => 'Audit domain validation failed'], $decoded);
    }

    public function testRunAuditReturns400OnInvalidArgumentException(): void
    {
        $tenantId = 'tenant-123';

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willThrowException(new InvalidArgumentException('Invalid tenant identifier'));

        $response = $this->controller->runAudit($this->requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['error' => 'Invalid tenant identifier'], $decoded);
    }

    public function testRunAuditReturns500OnGenericException(): void
    {
        $tenantId = 'tenant-123';

        $this->serviceMock->expects($this->once())
            ->method('runAudit')
            ->with($tenantId)
            ->willThrowException(new Exception('Database connection lost'));

        $response = $this->controller->runAudit($this->requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['error' => 'An internal server error occurred.'], $decoded);
    }

    public function testListDiscrepanciesReturns200OnSuccess(): void
    {
        $tenantId = 'tenant-123';
        $now = new \DateTimeImmutable('2026-03-30T12:00:00Z');
        $resolved = new \DateTimeImmutable('2026-03-30T13:00:00Z');

        $discrepancy = new AuditDiscrepancy(
            id: 'disc-1',
            tenantId: $tenantId,
            type: 'SHOPIFY_STOCK_MISMATCH',
            referenceId: 'SKU-1:LOC-1',
            externalRefId: 'ext-1',
            description: 'Mismatch description',
            status: 'RESOLVED',
            occurredAt: $now,
            resolvedAt: $resolved,
            resolutionNotes: 'Resolved notes'
        );

        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('status')
            ->willReturn('RESOLVED');

        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->with($tenantId, 'RESOLVED')
            ->willReturn([$discrepancy]);

        $response = $this->controller->listDiscrepancies($this->requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        $expected = [
            'discrepancies' => [
                [
                    'id' => 'disc-1',
                    'tenantId' => $tenantId,
                    'type' => 'SHOPIFY_STOCK_MISMATCH',
                    'referenceId' => 'SKU-1:LOC-1',
                    'externalRefId' => 'ext-1',
                    'description' => 'Mismatch description',
                    'status' => 'RESOLVED',
                    'occurredAt' => $now->format(\DateTimeInterface::ATOM),
                    'resolvedAt' => $resolved->format(\DateTimeInterface::ATOM),
                    'resolutionNotes' => 'Resolved notes',
                ]
            ]
        ];
        $this->assertEquals($expected, $decoded);
    }

    public function testListDiscrepanciesReturns500OnGenericException(): void
    {
        $tenantId = 'tenant-123';

        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('status')
            ->willReturn(null);

        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->with($tenantId, null)
            ->willThrowException(new Exception('Database query error'));

        $response = $this->controller->listDiscrepancies($this->requestMock, $tenantId);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['error' => 'An internal server error occurred.'], $decoded);
    }

    public function testResolveDiscrepancyReturns200OnSuccess(): void
    {
        $tenantId = 'tenant-123';
        $id = 'disc-1';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->with(['notes' => 'required|string'])
            ->willReturn(['notes' => 'Resolved by admin']);

        $this->serviceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with($tenantId, $id, 'Resolved by admin')
            ->willReturn(true);

        $response = $this->controller->resolveDiscrepancy($this->requestMock, $tenantId, $id);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['success' => true], $decoded);
    }

    public function testResolveDiscrepancyReturns404WhenNotFoundOrAlreadyResolved(): void
    {
        $tenantId = 'tenant-123';
        $id = 'disc-nonexistent';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->with(['notes' => 'required|string'])
            ->willReturn(['notes' => 'Notes']);

        $this->serviceMock->expects($this->once())
            ->method('resolveDiscrepancy')
            ->with($tenantId, $id, 'Notes')
            ->willReturn(false);

        $response = $this->controller->resolveDiscrepancy($this->requestMock, $tenantId, $id);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['error' => 'Discrepancy not found or already resolved'], $decoded);
    }

    public function testResolveDiscrepancyReturns400OnValidationException(): void
    {
        $tenantId = 'tenant-123';
        $id = 'disc-1';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->with(['notes' => 'required|string'])
            ->willThrowException(new DomainException('Validation failed: notes required'));

        $response = $this->controller->resolveDiscrepancy($this->requestMock, $tenantId, $id);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['error' => 'Validation failed: notes required'], $decoded);
    }

    public function testResolveDiscrepancyReturns500OnGenericException(): void
    {
        $tenantId = 'tenant-123';
        $id = 'disc-1';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->with(['notes' => 'required|string'])
            ->willThrowException(new Exception('Unexpected error'));

        $response = $this->controller->resolveDiscrepancy($this->requestMock, $tenantId, $id);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $this->assertEquals(['error' => 'An internal server error occurred.'], $decoded);
    }
}
