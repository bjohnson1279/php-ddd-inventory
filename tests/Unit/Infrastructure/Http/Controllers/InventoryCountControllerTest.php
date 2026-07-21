<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\InventoryCountController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Inventory\UseCases\StartInventoryCount;
use InventoryApp\Application\Inventory\UseCases\RecordCountItem;
use InventoryApp\Application\Inventory\UseCases\CompleteInventoryCount;
use InventoryApp\Infrastructure\Http\Response;
use Exception;

class InventoryCountControllerTest extends TestCase
{
    private $controller;

    protected function setUp(): void
    {
        $this->controller = new InventoryCountController();
    }

    public function testStartReturns201OnSuccess(): void
    {
        $useCase = $this->createMock(StartInventoryCount::class);
        $useCase->expects($this->once())->method('execute');

        $request = $this->createMock(RequestInterface::class);

        $response = $this->controller->start($request, $useCase);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('started successfully', $response->getContent());
    }

    public function testRecordItemReturns200OnSuccess(): void
    {
        $useCase = $this->createMock(RecordCountItem::class);
        $useCase->expects($this->once())->method('execute')
            ->with('c-1', 'SKU-A', 'LOC-A', 10);

        $request = $this->createMock(RequestInterface::class);
        $request->method('validate')->willReturn(['sku' => 'SKU-A', 'location_id' => 'LOC-A', 'quantity' => 10]);

        $response = $this->controller->recordItem('c-1', $request, $useCase);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('recorded successfully', $response->getContent());
    }

    public function testCompleteReturns200OnSuccess(): void
    {
        $useCase = $this->createMock(CompleteInventoryCount::class);
        $useCase->expects($this->once())->method('execute')->with('c-1');

        $response = $this->controller->complete('c-1', $useCase);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('completed', $response->getContent());
    }

    public function testControllerReturns400OnException(): void
    {
        $useCase = $this->createMock(CompleteInventoryCount::class);
        $useCase->method('execute')->willThrowException(new \DomainException('Kaboom'));

        $response = $this->controller->complete('c-1', $useCase);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Kaboom', $response->getContent());
    }
}
