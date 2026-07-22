<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\CatalogController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Catalog\UseCases\CreateProductCatalog;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Application\Catalog\UseCases\AddVariant;
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

            ->willThrowException(new \Exception('Database connection failed.'));


        $this->assertEquals(500, $response->getStatusCode());

        $this->assertEquals('An internal server error occurred.', $content['error']);
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
            ->willReturn($validData);

        $this->createProductMock->expects($this->once())
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
            ->willThrowException(new InvalidArgumentException('Invalid input'));

        $this->createProductMock->expects($this->never())
            ->method('execute');


        $this->assertEquals('Invalid input', $body['error']);
    }

    public function test_it_handles_create_product_generic_exception(): void
    {
            ->willThrowException(new Exception('Database connection failed'));



        $this->assertEquals('An internal server error occurred.', $body['error']);
    }

    public function test_it_adds_variant_successfully(): void
    {
        $productId = 'test-product-id';
            'sku' => 'TEST-SKU',
            'attributes' => ['color' => 'red'],
            'price' => 10.99


        $this->addVariantMock->expects($this->once())
                $productId,
                $validData['sku'],
                $validData['attributes'],
                $validData['price']

        $response = $this->controller->addVariant($this->requestMock, $productId, $this->addVariantMock);

        $this->assertEquals('Variant added successfully', $body['message']);
    }

    public function test_it_handles_add_variant_validation_error(): void
    {


        $this->addVariantMock->expects($this->never())


    }

    public function test_it_handles_add_variant_generic_exception(): void
    {




    }
}
