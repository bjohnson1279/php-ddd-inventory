<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use Exception;

class KitControllerTest extends TestCase
{
    private KitController $controller;

    protected function setUp(): void
    {
        $this->controller = new KitController();
    }

    public function testShowBySkuReturns200WithKit()
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $repoMock = $this->createMock(KitRepositoryInterface::class);

        $kit = new Kit('id-123', 'SKU-123', 'Test Kit');
        $kit->addComponent('var-1', 2);

        $repoMock->expects($this->once())
            ->method('findBySku')
            ->with('SKU-123')
            ->willReturn($kit);

        $response = $this->controller->showBySku($requestMock, 'SKU-123', $repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('id-123', $data['id']);
        $this->assertEquals('SKU-123', $data['sku']);
        $this->assertEquals('Test Kit', $data['name']);
        $this->assertCount(1, $data['components']);
        $this->assertEquals('var-1', $data['components'][0]['variant_id']);
        $this->assertEquals(2, $data['components'][0]['quantity']);
    }

    public function testShowBySkuReturns404WhenKitNotFound()
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $repoMock = $this->createMock(KitRepositoryInterface::class);

        $repoMock->expects($this->once())
            ->method('findBySku')
            ->with('SKU-404')
            ->willReturn(null);

        $response = $this->controller->showBySku($requestMock, 'SKU-404', $repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Kit not found', $data['error']);
    }

    public function testShowBySkuReturns400OnDomainException()
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $repoMock = $this->createMock(KitRepositoryInterface::class);

        $repoMock->expects($this->once())
            ->method('findBySku')
            ->with('SKU-ERR')
            ->willThrowException(new \DomainException('Domain error message'));

        $response = $this->controller->showBySku($requestMock, 'SKU-ERR', $repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Domain error message', $data['error']);
    }

    public function testShowBySkuReturns500OnUnexpectedException()
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $repoMock = $this->createMock(KitRepositoryInterface::class);

        $repoMock->expects($this->once())
            ->method('findBySku')
            ->with('SKU-FATAL')
            ->willThrowException(new \Exception('Database down'));

        $response = $this->controller->showBySku($requestMock, 'SKU-FATAL', $repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $data['error']);
    }
}
