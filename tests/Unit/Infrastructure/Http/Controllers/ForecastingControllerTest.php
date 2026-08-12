<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ForecastingController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use Illuminate\Database\Capsule\Manager as DB;

class ForecastingControllerTest extends TestCase
{
    private ForecastingController $controller;

    protected function setUp(): void
    {
        $this->controller = new ForecastingController();
    }

    public function testGetStockVelocityReportReturns400WhenVariantIdIsMissing(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('variantId')
            ->willReturn(null);

        $response = $this->controller->getStockVelocityReport($requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing required parameter: variantId', $response->getContent());
    }

    public function testGetStockVelocityReportReturns200WithVelocityData(): void
    {
        $pdoMock = $this->createMock(\PDO::class);

        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([
            (object)[
                'bucket' => '2023-10-02',
                'unitsDispatched' => 10,
                'unitsReceived' => 100,
                'transactionCount' => 2
            ],
            (object)[
                'bucket' => '2023-10-01',
                'unitsDispatched' => 50,
                'unitsReceived' => 20,
                'transactionCount' => 5
            ]
        ]);

        $pdoMock->method('prepare')->willReturn($stmtMock);

        $capsule = new DB();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);

        $capsule->getConnection()->setPdo($pdoMock);
        $capsule->setAsGlobal();

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('variantId')
            ->willReturn('VAR-123');

        $response = $this->controller->getStockVelocityReport($requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertCount(2, $content);

        $this->assertEquals('2023-10-02', $content[0]['bucket']);
        $this->assertEquals(10, $content[0]['unitsDispatched']);
        $this->assertEquals(100, $content[0]['unitsReceived']);
        $this->assertEquals(2, $content[0]['transactionCount']);
    }

    public function testGetStockVelocityReportReturns500OnDatabaseException(): void
    {
        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('prepare')->willThrowException(new \PDOException('Connection failed'));

        $capsule = new DB();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);
        $capsule->getConnection()->setPdo($pdoMock);
        $capsule->setAsGlobal();

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('query')
            ->with('variantId')
            ->willReturn('VAR-123');

        $response = $this->controller->getStockVelocityReport($requestMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }
}
