<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\AuthController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Identity\UseCases\RegisterUser;
use InventoryApp\Application\Identity\UseCases\AuthenticateUser;
use InventoryApp\Infrastructure\Http\Response;
use Exception;
use InvalidArgumentException;

class AuthControllerTest extends TestCase
{
    private AuthController $controller;
    private $registerUserMock;
    private $authenticateUserMock;
    private $requestMock;

    protected function setUp(): void
    {
        $this->controller = new AuthController();
    }

    public function testRegisterReturns201OnSuccess(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->with([
                'id'        => 'required|string',
                'tenant_id' => 'required|string',
                'email'     => 'required|string',
                'password'  => 'required|string',
                'name'      => 'required|string',
            ])
            ->willReturn([
                'id'        => 'user-123',
                'tenant_id' => 'tenant-1',
                'email'     => 'test@example.com',
                'password'  => 'secret',
                'name'      => 'Test User',
            ]);

        $useCaseMock = $this->createMock(RegisterUser::class);
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->with('user-123', 'tenant-1', 'test@example.com', 'secret', 'Test User');

        // Act
        $response = $this->controller->register($requestMock, $useCaseMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('User registered successfully.', $content['message']);
    }

    public function testRegisterReturns400OnDomainException(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'id'        => 'user-123',
                'tenant_id' => 'tenant-1',
                'email'     => 'test@example.com',
                'password'  => 'secret',
                'name'      => 'Test User',
            ]);

        $useCaseMock = $this->createMock(RegisterUser::class);
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \DomainException('A user with email \'test@example.com\' already exists for this tenant.'));

        // Act
        $response = $this->controller->register($requestMock, $useCaseMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('A user with email \'test@example.com\' already exists for this tenant.', $content['error']);
    }

    public function testRegisterReturns400OnInvalidArgumentException(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'id'        => 'user-123',
                'tenant_id' => 'tenant-1',
                'email'     => 'invalid-email',
                'password'  => 'secret',
                'name'      => 'Test User',
            ]);

        $useCaseMock = $this->createMock(RegisterUser::class);
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new InvalidArgumentException('Invalid email format.'));

        // Act
        $response = $this->controller->register($requestMock, $useCaseMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Invalid email format.', $content['error']);
    }

    public function testRegisterReturns500OnUnexpectedException(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'id'        => 'user-123',
                'tenant_id' => 'tenant-1',
                'email'     => 'test@example.com',
                'password'  => 'secret',
                'name'      => 'Test User',
            ]);

        $useCaseMock = $this->createMock(RegisterUser::class);
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception('Database connection error'));

        // Act
        $response = $this->controller->register($requestMock, $useCaseMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('An internal server error occurred.', $content['error']);
    }

    public function testLoginReturns401OnException(): void
    {
        // Arrange
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'email'     => 'test@example.com',
                'password'  => 'wrong-password',
                'tenant_id' => 'tenant-1',
            ]);

        $useCaseMock = $this->createMock(AuthenticateUser::class);
        $useCaseMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \DomainException('Invalid credentials.'));

        // Act
        $response = $this->controller->login($requestMock, $useCaseMock);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Invalid credentials.', $content['error']);
    }
}
