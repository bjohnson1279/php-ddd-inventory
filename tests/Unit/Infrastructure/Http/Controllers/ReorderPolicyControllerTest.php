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
use Exception;

class ReorderPolicyControllerTest extends TestCase
{
    private ReorderPolicyController $controller;

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

        $container = ServiceContainer::getInstance();
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
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $repoMock = $this->createMock(ReorderPolicyRepositoryInterface::class);

        $productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $ledgerRepoMock = $this->createMock(LedgerRepositoryInterface::class);
        $poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $serviceMock = $this->createMock(ReorderPolicyService::class);

        $container = ServiceContainer::getInstance();
        $container->instance(ProductRepositoryInterface::class, $productRepoMock);
        $container->instance(LedgerRepositoryInterface::class, $ledgerRepoMock);
        $container->instance(PurchaseOrderRepositoryInterface::class, $poRepoMock);
        $container->instance(ReorderPolicyService::class, $serviceMock);

        $serviceMock->expects($this->once())
            ->method('evaluatePolicies')
            ->willThrowException(new Exception("Database connection failed"));

        // Act
        $response = $this->controller->evaluate($requestMock, $repoMock);

        // Assert
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
