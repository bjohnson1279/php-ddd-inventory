<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\CatalogController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Infrastructure\Http\Response;

class CatalogControllerTest extends TestCase
{
    private CatalogController $controller;

    protected function setUp(): void
    {
        $this->controller = new CatalogController();
    }

    public function test_create_product_returns_400_on_expected_exceptions(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'name' => 'Test Product',
                'description' => 'Test Description',
                'department' => 'Test Department'
            ]);

        $useCaseMock = $this->createMock(CreateProductCatalog::class);
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \InvalidArgumentException('Invalid data provided.'));

        // Act
        $response = $this->controller->createProduct($requestMock, $useCaseMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Invalid data provided.', $content['error']);
    }

    public function test_create_product_returns_500_on_internal_server_error(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'name' => 'Test Product',
                'description' => 'Test Description',
                'department' => 'Test Department'
            ]);

        $useCaseMock = $this->createMock(CreateProductCatalog::class);
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database connection failed.'));

        // Act
        $response = $this->controller->createProduct($requestMock, $useCaseMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
