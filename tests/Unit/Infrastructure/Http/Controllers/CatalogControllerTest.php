<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\CatalogController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
use InventoryApp\Infrastructure\Http\Response;
use InvalidArgumentException;
use Exception;

class CatalogControllerTest extends TestCase
{
    private CatalogController $controller;
    private $createProductMock;
    private $addVariantMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->controller = new CatalogController();
        $this->createProductMock = $this->createMock(CreateProductCatalog::class);
        $this->addVariantMock = $this->createMock(AddVariant::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
    }

    public function test_it_creates_product_successfully(): void
    {
        $validData = [
            'name' => 'Test Product',
            'description' => 'Test Description',
            'department' => 'Test Department'
        ];

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn($validData);

        $this->createProductMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->isType('string'),
                $validData['name'],
                $validData['description'],
                $validData['department']
            );

        $response = $this->controller->createProduct($this->requestMock, $this->createProductMock);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Catalog product created successfully', $body['message']);
        $this->assertArrayHasKey('id', $body);
    }

    public function test_it_handles_create_product_validation_error(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('Invalid input'));

        $this->createProductMock->expects($this->never())
            ->method('execute');

        $response = $this->controller->createProduct($this->requestMock, $this->createProductMock);

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid input', $body['error']);
    }

    public function test_it_handles_create_product_generic_exception(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new Exception('Database connection failed'));

        $this->createProductMock->expects($this->never())
            ->method('execute');

        $response = $this->controller->createProduct($this->requestMock, $this->createProductMock);

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $body['error']);
    }

    public function test_it_adds_variant_successfully(): void
    {
        $productId = 'test-product-id';
        $validData = [
            'sku' => 'TEST-SKU',
            'attributes' => ['color' => 'red'],
            'price' => 10.99
        ];

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn($validData);

        $this->addVariantMock->expects($this->once())
            ->method('execute')
            ->with(
                $productId,
                $this->isType('string'),
                $validData['sku'],
                $validData['attributes'],
                $validData['price']
            );

        $response = $this->controller->addVariant($this->requestMock, $productId, $this->addVariantMock);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Variant added successfully', $body['message']);
        $this->assertArrayHasKey('id', $body);
    }

    public function test_it_handles_add_variant_validation_error(): void
    {
        $productId = 'test-product-id';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('Invalid input'));

        $this->addVariantMock->expects($this->never())
            ->method('execute');

        $response = $this->controller->addVariant($this->requestMock, $productId, $this->addVariantMock);

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid input', $body['error']);
    }

    public function test_it_handles_add_variant_generic_exception(): void
    {
        $productId = 'test-product-id';

        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new Exception('Database connection failed'));

        $this->addVariantMock->expects($this->never())
            ->method('execute');

        $response = $this->controller->addVariant($this->requestMock, $productId, $this->addVariantMock);

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $body['error']);
    }
}
