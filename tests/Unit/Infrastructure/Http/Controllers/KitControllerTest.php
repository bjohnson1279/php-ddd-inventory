<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use Exception;

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

    public function testAddComponentSuccessfully(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'variant_id' => 'required|string',
                'quantity'   => 'required|integer',
            ])
            ->willReturn([
                'variant_id' => 'VAR-123',
                'quantity'   => 5,
            ]);

        $kitMock = $this->createMock(Kit::class);
        $kitMock->expects($this->once())
            ->method('addComponent')
            ->with('VAR-123', 5);

        $this->repoMock->expects($this->once())
            ->method('findOrFail')
            ->with('KIT-123')
            ->willReturn($kitMock);

        $this->repoMock->expects($this->once())
            ->method('save')
            ->with($kitMock);

        $response = $this->controller->addComponent($this->requestMock, 'KIT-123', $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Component added\/updated successfully', $response->getContent());
    }

    public function testAddComponentValidationFailure(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \InvalidArgumentException('Validation failed'));

        $this->repoMock->expects($this->never())->method('findOrFail');
        $this->repoMock->expects($this->never())->method('save');

        $response = $this->controller->addComponent($this->requestMock, 'KIT-123', $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Validation failed', $response->getContent());
    }

    public function testAddComponentKitNotFound(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'variant_id' => 'VAR-123',
                'quantity'   => 5,
            ]);

        $this->repoMock->expects($this->once())
            ->method('findOrFail')
            ->with('KIT-123')
            ->willThrowException(new \DomainException('Kit not found'));

        $this->repoMock->expects($this->never())->method('save');

        $response = $this->controller->addComponent($this->requestMock, 'KIT-123', $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Kit not found', $response->getContent());
    }

    public function testAddComponentInternalError(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'variant_id' => 'VAR-123',
                'quantity'   => 5,
            ]);

        $this->repoMock->expects($this->once())
            ->method('findOrFail')
            ->with('KIT-123')
            ->willThrowException(new \Exception('Database failure'));

        $this->repoMock->expects($this->never())->method('save');

        $response = $this->controller->addComponent($this->requestMock, 'KIT-123', $this->repoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }
}
