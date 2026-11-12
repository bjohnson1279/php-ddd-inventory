<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Inventory\Services\InventoryService;
use InventoryApp\Infrastructure\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;

class KitControllerTest extends TestCase
{
    private KitController $controller;
    private $capsule;

    protected function setUp(): void
    {
        $this->controller = new KitController();

        // Setup SQLite for capsule transaction
        $this->capsule = new Capsule;
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['auth.user_id']);
        $this->capsule->getConnection()->disconnect();
        $this->capsule = null;
    }

    public function test_sell_returns_200_on_success(): void
    {
        // Arrange
        $_SERVER['auth.user_id'] = 'user-123';

        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'quantity' => '2',
                'sale_id'  => 'sale-456',
            ]);

        $kitRepoMock = $this->createMock(KitRepositoryInterface::class);
        $kit = new Kit('kit-1', 'KIT-SKU-1', 'Test Kit');
        $kit->addComponent('VAR-1', 1);

        $kitRepoMock->expects($this->once())
            ->method('findOrFail')
            ->with('kit-1')
            ->willReturn($kit);

        $inventoryServiceMock = $this->createMock(InventoryService::class);
        $inventoryServiceMock->expects($this->once())
            ->method('decrementForKitSale')
            ->with($kit, 2, 'sale-456', 'user-123');

        // Act
        $response = $this->controller->sell($requestMock, 'kit-1', $kitRepoMock, $inventoryServiceMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Kit sold successfully and component inventories decremented.', $content['message']);
    }

    public function test_sell_returns_400_on_domain_exception(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'quantity' => '2',
                'sale_id'  => 'sale-456',
            ]);

        $kitRepoMock = $this->createMock(KitRepositoryInterface::class);
        $kitRepoMock->expects($this->once())
            ->method('findOrFail')
            ->willThrowException(new \DomainException('Insufficient inventory'));

        $inventoryServiceMock = $this->createMock(InventoryService::class);

        // Act
        $response = $this->controller->sell($requestMock, 'kit-1', $kitRepoMock, $inventoryServiceMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Insufficient inventory', $content['error']);
    }

    public function test_sell_returns_500_on_internal_error(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'quantity' => '2',
                'sale_id'  => 'sale-456',
            ]);

        $kitRepoMock = $this->createMock(KitRepositoryInterface::class);
        $kitRepoMock->expects($this->once())
            ->method('findOrFail')
            ->willThrowException(new Exception('Database down'));

        $inventoryServiceMock = $this->createMock(InventoryService::class);

        // Act
        $response = $this->controller->sell($requestMock, 'kit-1', $kitRepoMock, $inventoryServiceMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }

    public function test_create_returns_201_and_instantiates_kit_on_success(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'sku'  => 'required|string',
                'name' => 'required|string',
            ])
            ->willReturn([
                'sku'  => 'KIT-SKU-NEW',
                'name' => 'New Bundle Kit',
            ]);

        $kitRepoMock = $this->createMock(KitRepositoryInterface::class);
        $kitRepoMock->expects($this->once())
            ->method('findBySku')
            ->with('KIT-SKU-NEW')
            ->willReturn(null);

        $kitRepoMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Kit $kit) {
                return $kit->sku === 'KIT-SKU-NEW'
                    && $kit->name === 'New Bundle Kit'
                    && !empty($kit->id);
            }));

        $response = $this->controller->create($requestMock, $kitRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('Kit created successfully', $content['message']);
        $this->assertEquals('KIT-SKU-NEW', $content['sku']);
        $this->assertEquals('New Bundle Kit', $content['name']);
        $this->assertNotEmpty($content['id']);
    }

    public function test_create_returns_400_when_validation_fails(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willThrowException(new \InvalidArgumentException('Validation failed: sku is required'));

        $kitRepoMock = $this->createMock(KitRepositoryInterface::class);
        $kitRepoMock->expects($this->never())->method('findBySku');
        $kitRepoMock->expects($this->never())->method('save');

        $response = $this->controller->create($requestMock, $kitRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Validation failed: sku is required', $content['error']);
    }

    public function test_create_returns_400_when_kit_already_exists(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku'  => 'EXISTING-SKU',
                'name' => 'Existing Kit',
            ]);

        $kitRepoMock = $this->createMock(KitRepositoryInterface::class);
        $existingKit = new Kit('kit-existing-id', 'EXISTING-SKU', 'Existing Kit');
        $kitRepoMock->expects($this->once())
            ->method('findBySku')
            ->with('EXISTING-SKU')
            ->willReturn($existingKit);

        $kitRepoMock->expects($this->never())->method('save');

        $response = $this->controller->create($requestMock, $kitRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Kit with this SKU already exists.', $content['error']);
    }

    public function test_create_returns_500_on_unexpected_exception(): void
    {
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'sku'  => 'KIT-ERR',
                'name' => 'Error Kit',
            ]);

        $kitRepoMock = $this->createMock(KitRepositoryInterface::class);
        $kitRepoMock->expects($this->once())
            ->method('findBySku')
            ->willThrowException(new Exception('Database connection lost'));

        $kitRepoMock->expects($this->never())->method('save');

        $response = $this->controller->create($requestMock, $kitRepoMock);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }
}
