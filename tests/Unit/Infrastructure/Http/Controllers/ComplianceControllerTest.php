<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ComplianceController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Compliance\Services\ComplianceLedgerService;
use InventoryApp\Domain\Compliance\Repositories\ComplianceLedgerRepositoryInterface;
use InventoryApp\Domain\Compliance\Entities\ComplianceLedgerEntry;
use Exception;
use DateTime;

class ComplianceControllerTest extends TestCase
{
    private ComplianceController $controller;
    private $repoMock;
    private $serviceMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->repoMock = $this->createMock(ComplianceLedgerRepositoryInterface::class);
        $this->serviceMock = $this->createMock(ComplianceLedgerService::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->controller = new ComplianceController($this->repoMock, $this->serviceMock);
    }

    public function testVerifyReturns200WithValidResult(): void
    {
        $expectedResult = ['isValid' => true];

        $this->serviceMock->expects($this->once())
            ->method('validateLedger')
            ->with('system')
            ->willReturn($expectedResult);

        $response = $this->controller->verify($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['isValid']);
    }

    public function testVerifyReturns200WithInvalidResult(): void
    {
        $expectedResult = [
            'isValid' => false,
            'failedSequenceNumber' => 2,
            'reason' => 'Chaining hash mismatch.'
        ];

        $this->serviceMock->expects($this->once())
            ->method('validateLedger')
            ->with('system')
            ->willReturn($expectedResult);

        $response = $this->controller->verify($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['isValid']);
        $this->assertEquals(2, $content['failedSequenceNumber']);
        $this->assertEquals('Chaining hash mismatch.', $content['reason']);
    }

    public function testVerifyReturns500OnException(): void
    {
        $this->serviceMock->expects($this->once())
            ->method('validateLedger')
            ->willThrowException(new Exception('Ledger validation failed'));

        $response = $this->controller->verify($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }

    public function testListReturns200WithOrderedEntries(): void
    {
        $entry1 = new ComplianceLedgerEntry(
            'entry-1',
            'system',
            'actor-1',
            'STOCK_ADJUSTED',
            1,
            str_repeat('0', 64),
            'hash-1',
            'sig-1',
            json_encode(['sku' => 'SKU-1']),
            new DateTime('2026-01-01T00:00:00Z')
        );

        $entry2 = new ComplianceLedgerEntry(
            'entry-2',
            'system',
            'actor-2',
            'STOCK_ADJUSTED',
            2,
            'hash-1',
            'hash-2',
            'sig-2',
            json_encode(['sku' => 'SKU-2']),
            new DateTime('2026-01-02T00:00:00Z')
        );

        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->with('system')
            ->willReturn([$entry1, $entry2]);

        $response = $this->controller->list($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertCount(2, $content);
        // Verify reverse order by sequenceNumber (sequenceNumber 2 first, then 1)
        $this->assertEquals(2, $content[0]['sequenceNumber']);
        $this->assertEquals(1, $content[1]['sequenceNumber']);
        $this->assertEquals(['sku' => 'SKU-2'], $content[0]['payload']);
    }

    public function testListReturns500OnException(): void
    {
        $this->repoMock->expects($this->once())
            ->method('findAll')
            ->willThrowException(new Exception('Database error'));

        $response = $this->controller->list($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }

    public function testReconstructReturns200WithReconstructedState(): void
    {
        $expectedResult = [
            'timestamp' => '2026-01-01T00:00:00+00:00',
            'tenantId' => 'system',
            'eventsReplayedCount' => 3,
            'lastSequenceNumber' => 3,
            'stockLevels' => [],
            'binConfigurations' => [],
            'accountBalances' => []
        ];

        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('timestamp')
            ->willReturn('2026-01-01T00:00:00Z');

        $this->serviceMock->expects($this->once())
            ->method('reconstructState')
            ->with('system', '2026-01-01T00:00:00Z')
            ->willReturn($expectedResult);

        $response = $this->controller->reconstruct($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($expectedResult, $content);
    }

    public function testReconstructReturns500OnException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('timestamp')
            ->willReturn(null);

        $this->serviceMock->expects($this->once())
            ->method('reconstructState')
            ->willThrowException(new Exception('Reconstruction failed'));

        $response = $this->controller->reconstruct($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Failed to reconstruct state.', $content['error']);
    }

    public function testReplayReturns200WithAuditEvents(): void
    {
        $expectedResult = [
            [
                'sequenceNumber' => 1,
                'eventType' => 'STOCK_ADJUSTED',
                'timestamp' => '2026-01-01T00:00:00+00:00',
                'hash' => 'hash-1',
                'previousHash' => str_repeat('0', 64),
                'payload' => ['sku' => 'SKU-1']
            ]
        ];

        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('timestamp')
            ->willReturn('2026-01-01T00:00:00Z');

        $this->serviceMock->expects($this->once())
            ->method('replayAudit')
            ->with('system', '2026-01-01T00:00:00Z')
            ->willReturn($expectedResult);

        $response = $this->controller->replay($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($expectedResult, $content);
    }

    public function testReplayReturns500OnException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('query')
            ->with('timestamp')
            ->willReturn(null);

        $this->serviceMock->expects($this->once())
            ->method('replayAudit')
            ->willThrowException(new Exception('Replay failed'));

        $response = $this->controller->replay($this->requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Failed to replay audit.', $content['error']);
    }
}
