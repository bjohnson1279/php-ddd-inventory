<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\InventoryController;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\GetStockLevel;
use InventoryApp\Infrastructure\Http\Response;

class InventoryControllerTest extends TestCase
{
    private $controller;

    protected function setUp(): void
    {
        $this->controller = new InventoryController();
    }

    public function testReceiveReturns200OnSuccess(): void
    {
        $useCase = $this->createMock(ReceiveStock::class);
        $useCase->expects($this->once())->method('execute');

        $request = $this->getMockBuilder(\stdClass::class)->addMethods(['validate'])->getMock();
        $request->method('validate')->willReturn([
            'sku' => 'SKU-1',
            'quantity' => 5,
            'location_id' => 'LOC-1'
        ]);

        $response = $this->controller->receive($request, $useCase);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testStockLevelReturns200WithData(): void
    {
        $useCase = $this->createMock(GetStockLevel::class);
        $useCase->method('execute')->willReturn(10);

        $request = $this->getMockBuilder(\stdClass::class)->addMethods(['query'])->getMock();
        $request->method('query')->with('location_id')->willReturn('LOC-1');

        $response = $this->controller->stockLevel($request, 'SKU-1', $useCase);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('"stock":10', $response->getContent());
    }
}
