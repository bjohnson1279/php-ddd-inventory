<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use Exception;
use DomainException;

class KitControllerTest extends TestCase
{
    private KitController $controller;
    private $requestMock;
    private $repoMock;

    protected function setUp(): void
    {
        $this->controller = new KitController();
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->repoMock = $this->createMock(KitRepositoryInterface::class);
    }

    public function testShowReturns200AndSerializedKitOnSuccess(): void
    {
        $kitId = 'k-123';
        $kit = new Kit($kitId, 'SKU-123', 'Test Kit');
        $kit->addComponent('v-1', 2);

        $this->repoMock->expects($this->once())
            ->method('findOrFail')
            ->with($kitId)
            ->willReturn($kit);

        $response = $this->controller->show($this->requestMock, $kitId, $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($kitId, $content['id']);
        $this->assertEquals('SKU-123', $content['sku']);
        $this->assertEquals('Test Kit', $content['name']);
        $this->assertCount(1, $content['components']);
        $this->assertEquals('v-1', $content['components'][0]['variant_id']);
        $this->assertEquals(2, $content['components'][0]['quantity']);
    }

    public function testShowReturns404OnDomainException(): void
    {
        $kitId = 'k-invalid';

        $this->repoMock->expects($this->once())
            ->method('findOrFail')
            ->with($kitId)
            ->willThrowException(new DomainException('Kit not found'));

        $response = $this->controller->show($this->requestMock, $kitId, $this->repoMock);

        $this->assertEquals(404, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Kit not found', $content['error']);
    }

    public function testShowReturns500OnGenericException(): void
    {
        $kitId = 'k-error';

        $this->repoMock->expects($this->once())
            ->method('findOrFail')
            ->with($kitId)
            ->willThrowException(new Exception('Database connection failed'));

        // Suppress error_log output for this test
        ob_start();
        $response = $this->controller->show($this->requestMock, $kitId, $this->repoMock);
        ob_end_clean();

        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
