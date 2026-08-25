<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Inventory\UseCases\AssembleKit;
use InventoryApp\Infrastructure\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use DomainException;
use Exception;
use InvalidArgumentException;

class KitControllerTest extends TestCase
{
    private KitController $controller;
    private $assembleKitMock;

    protected function setUp(): void
    {
        $this->controller = new KitController();
        $this->assembleKitMock = $this->createMock(AssembleKit::class);

        // Setup SQLite connection for Capsule if not already booted
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'prefix'    => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    public function testAssembleReturns200OnSuccess(): void
    {
        $this->assembleKitMock->expects($this->once())->method('execute');

        $request = $this->createMock(RequestInterface::class);
        $request->method('validate')->willReturn([
            'kitSku'      => 'TEST-KIT',
            'quantity'    => 5,
            'locationId'  => 'LOC-123',
            'referenceId' => 'REF-456',
        ]);

        $_SERVER['auth.tenant_id'] = 'tenant-1';
        $_SERVER['auth.user_id'] = 'user-1';

        $response = $this->controller->assemble($request, $this->assembleKitMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Successfully assembled', $response->getContent());
    }

    public function testAssembleReturns400OnDomainException(): void
    {
        $this->assembleKitMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new DomainException('Insufficient components for assembly.'));

        $request = $this->createMock(RequestInterface::class);
        $request->method('validate')->willReturn([
            'kitSku'      => 'TEST-KIT',
            'quantity'    => 5,
            'locationId'  => 'LOC-123',
            'referenceId' => 'REF-456',
        ]);

        $response = $this->controller->assemble($request, $this->assembleKitMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Insufficient components', $response->getContent());
    }

    public function testAssembleReturns400OnInvalidArgumentException(): void
    {
        $this->assembleKitMock->expects($this->never())
            ->method('execute');

        $request = $this->createMock(RequestInterface::class);
        $request->method('validate')->willThrowException(new InvalidArgumentException('Invalid quantity.'));

        $response = $this->controller->assemble($request, $this->assembleKitMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Invalid quantity', $response->getContent());
    }

    public function testAssembleReturns500OnGeneralException(): void
    {
        $this->assembleKitMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception('Database connection failed.'));

        $request = $this->createMock(RequestInterface::class);
        $request->method('validate')->willReturn([
            'kitSku'      => 'TEST-KIT',
            'quantity'    => 5,
            'locationId'  => 'LOC-123',
            'referenceId' => 'REF-456',
        ]);

        // Capture error_log output to prevent polluting test output
        ob_start();
        $response = $this->controller->assemble($request, $this->assembleKitMock);
        ob_end_clean();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('An internal server error occurred', $response->getContent());
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.tenant_id']);
        unset($_SERVER['auth.user_id']);
        parent::tearDown();
    }
}
