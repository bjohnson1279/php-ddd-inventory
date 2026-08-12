<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Infrastructure\Http\RequestInterface;
<<<<<<< HEAD
use InventoryApp\Application\Inventory\UseCases\DisassembleKit;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use InvalidArgumentException;
=======
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use Exception;
>>>>>>> origin/master

class KitControllerTest extends TestCase
{
    private KitController $controller;
<<<<<<< HEAD
    private $disassembleKitMock;
=======
    private $kitRepositoryMock;
>>>>>>> origin/master

    protected function setUp(): void
    {
        $this->controller = new KitController();
<<<<<<< HEAD
        $this->disassembleKitMock = $this->createMock(DisassembleKit::class);

        // Mock Capsule connection for transactions
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.tenant_id']);
        unset($_SERVER['auth.user_id']);
        parent::tearDown();
    }

    public function testDisassembleWithValidRequest(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'kitSku'      => 'required|string',
                'quantity'    => 'required|integer',
                'locationId'  => 'required|string',
                'referenceId' => 'required|string',
            ])
            ->willReturn([
                'kitSku'      => 'KIT-123',
                'quantity'    => 5,
                'locationId'  => 'LOC-1',
                'referenceId' => 'REF-1',
            ]);

        // Capture $_SERVER auth data
        $_SERVER['auth.tenant_id'] = 'tenant-1';
        $_SERVER['auth.user_id'] = 'user-1';

        $this->disassembleKitMock->expects($this->once())
            ->method('execute')
            ->with([
                'tenantId'    => 'tenant-1',
                'locationId'  => 'LOC-1',
                'kitSku'      => 'KIT-123',
                'quantity'    => 5,
                'actorId'     => 'user-1',
                'referenceId' => 'REF-1',
            ]);

        $response = $this->controller->disassemble($requestMock, $this->disassembleKitMock);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            json_encode(['message' => 'Successfully disassembled 5 units of Kit KIT-123.']),
            $response->getContent()
        );
    }

    public function testDisassembleWithValidationException(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidArgumentException('Invalid quantity'));

        $response = $this->controller->disassemble($requestMock, $this->disassembleKitMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(
            json_encode(['error' => 'Invalid quantity']),
            $response->getContent()
        );
    }

    public function testDisassembleWithInternalException(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new Exception('Database error'));

        $response = $this->controller->disassemble($requestMock, $this->disassembleKitMock);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals(
            json_encode(['error' => 'An internal server error occurred.']),
            $response->getContent()
        );
=======
        $this->kitRepositoryMock = $this->createMock(KitRepositoryInterface::class);
>>>>>>> origin/master
    }
}
