<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
<<<<<<< HEAD
use InventoryApp\Infrastructure\Http\RequestInterface;
=======
<<<<<<< HEAD
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use Exception;
=======
use InventoryApp\Infrastructure\Http\RequestInterface;
<<<<<<< HEAD
use InventoryApp\Application\Inventory\UseCases\DisassembleKit;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use InvalidArgumentException;
=======
>>>>>>> origin/master
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use Exception;
<<<<<<< HEAD
use DomainException;
=======
>>>>>>> origin/master
>>>>>>> origin/master
>>>>>>> origin/master

class KitControllerTest extends TestCase
{
    private KitController $controller;
<<<<<<< HEAD
    private $requestMock;
    private $repoMock;
=======
<<<<<<< HEAD
    private $requestMock;
    private $repoMock;
=======
<<<<<<< HEAD
    private $disassembleKitMock;
=======
    private $kitRepositoryMock;
>>>>>>> origin/master
>>>>>>> origin/master
>>>>>>> origin/master

    protected function setUp(): void
    {
        $this->controller = new KitController();
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> origin/master
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->repoMock = $this->createMock(KitRepositoryInterface::class);
    }

<<<<<<< HEAD
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
=======
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
=======
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
>>>>>>> origin/master
>>>>>>> origin/master
    }
}
