<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ReorderPolicyController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Infrastructure\Http\Response;
use InvalidArgumentException;
use Exception;

class ReorderPolicyControllerTest extends TestCase
{
    private ReorderPolicyController $controller;
    private $requestMock;
    private $repoMock;

    protected function setUp(): void
    {
        $this->controller = new ReorderPolicyController();
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->repoMock = $this->createMock(ReorderPolicyRepositoryInterface::class);
    }

    public function testGetReturns200AndPolicyDataOnSuccess(): void
    {
        $sku = 'TEST-SKU';
        $locationId = 'LOC-1';

        $policy = new ReorderPolicy(
            'policy-123',
            new SKU($sku),
            $locationId,
            10,
            20,
            5,
            true
        );

        $this->repoMock->expects($this->once())
            ->method('findBySkuAndLocation')
            ->with($this->callback(function (SKU $arg) use ($sku) {
                return $arg->getValue() === $sku;
            }), $locationId)
            ->willReturn($policy);

        $response = $this->controller->get($this->requestMock, $sku, $locationId, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('policy-123', $content['id']);
        $this->assertEquals($sku, $content['sku']);
        $this->assertEquals($locationId, $content['locationId']);
        $this->assertEquals(10, $content['reorderPoint']);
        $this->assertEquals(20, $content['reorderQuantity']);
        $this->assertEquals(5, $content['safetyStock']);
        $this->assertTrue($content['dynamicRopEnabled']);
    }

    public function testGetReturns404WhenPolicyNotFound(): void
    {
        $sku = 'NONEXISTENT-SKU';
        $locationId = 'LOC-1';

        $this->repoMock->expects($this->once())
            ->method('findBySkuAndLocation')
            ->willReturn(null);

        $response = $this->controller->get($this->requestMock, $sku, $locationId, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Reorder policy not found', $content['error']);
    }

    public function testGetReturns400OnValidationOrDomainException(): void
    {
        $sku = 'TEST-SKU';
        $locationId = 'LOC-1';
        $exceptionMessage = 'Invalid arguments provided.';

        $this->repoMock->expects($this->once())
            ->method('findBySkuAndLocation')
            ->willThrowException(new InvalidArgumentException($exceptionMessage));

        $response = $this->controller->get($this->requestMock, $sku, $locationId, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals($exceptionMessage, $content['error']);
    }

    public function testGetReturns500OnUnexpectedException(): void
    {
        $sku = 'TEST-SKU';
        $locationId = 'LOC-1';

        $this->repoMock->expects($this->once())
            ->method('findBySkuAndLocation')
            ->willThrowException(new Exception('Database connection failed.'));

        // Suppress error_log output during test
        ob_start();
        $response = $this->controller->get($this->requestMock, $sku, $locationId, $this->repoMock);
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}