<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ReorderPolicyController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Services\ReorderPolicyService;
use InventoryApp\Infrastructure\ServiceContainer;
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
    }

    protected function tearDown(): void
    {
        $container = ServiceContainer::getInstance();
        $container->forgetInstances();
        parent::tearDown();
    }

    public function test_evaluate_returns_200_with_results(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $repoMock = $this->createMock(ReorderPolicyRepositoryInterface::class);

        $productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $ledgerRepoMock = $this->createMock(LedgerRepositoryInterface::class);
        $poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $serviceMock = $this->createMock(ReorderPolicyService::class);

        $container->instance(ProductRepositoryInterface::class, $productRepoMock);
        $container->instance(LedgerRepositoryInterface::class, $ledgerRepoMock);
        $container->instance(PurchaseOrderRepositoryInterface::class, $poRepoMock);
        $container->instance(ReorderPolicyService::class, $serviceMock);

        $expectedResults = [
            [
                'sku' => 'TEST-SKU',
                'locationId' => 'LOC-1',
                'action' => 'reorder',
                'quantity' => 100
            ]
        ];

        $serviceMock->expects($this->once())
            ->method('evaluatePolicies')
            ->willReturn($expectedResults);

        // Act
        $response = $this->controller->evaluate($requestMock, $repoMock);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('results', $content);
        $this->assertEquals($expectedResults, $content['results']);
    }

    public function test_evaluate_returns_500_on_exception(): void
    {



            ->willThrowException(new Exception("Database connection failed"));


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

            ->willReturn(null);


        $this->assertEquals(404, $response->getStatusCode());

        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Reorder policy not found', $content['error']);
    }

    public function testGetReturns400OnValidationOrDomainException(): void
    {
        $exceptionMessage = 'Invalid arguments provided.';

            ->willThrowException(new InvalidArgumentException($exceptionMessage));


        $this->assertEquals(400, $response->getStatusCode());

        $this->assertEquals($exceptionMessage, $content['error']);
    }

    public function testGetReturns500OnUnexpectedException(): void
    {

            ->willThrowException(new Exception('Database connection failed.'));

        // Suppress error_log output during test
        ob_start();
        ob_end_clean();

        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
}
