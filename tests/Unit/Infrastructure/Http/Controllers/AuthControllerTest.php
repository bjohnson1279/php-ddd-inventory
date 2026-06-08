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
        $this->registerUserMock = $this->createMock(RegisterUser::class);
        $this->authenticateUserMock = $this->createMock(AuthenticateUser::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
    }

    public function testLoginSuccess(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'email'     => 'test@example.com',
                'password'  => 'password',
                'tenant_id' => 'tenant-1'
            ]);

        $this->authenticateUserMock->expects($this->once())
            ->method('execute')
            ->with('test@example.com', 'password', 'tenant-1')
            ->willReturn('valid-token');

        $response = $this->controller->login($this->requestMock, $this->authenticateUserMock);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(json_encode(['token' => 'valid-token']), $response->getContent());
    }

    public function testLoginThrowsExceptionAndReturns401(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'email'     => 'test@example.com',
                'password'  => 'password',
                'tenant_id' => 'tenant-1'
            ]);

        $this->authenticateUserMock->expects($this->once())
            ->method('execute')
            ->with('test@example.com', 'password', 'tenant-1')
            ->willThrowException(new Exception('Invalid credentials.'));

        $response = $this->controller->login($this->requestMock, $this->authenticateUserMock);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(json_encode(['error' => 'Invalid credentials.']), $response->getContent());
    }

    public function testLoginThrowsInvalidArgumentExceptionAndReturns401(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'email'     => 'test@example.com',
                'password'  => 'password',
                'tenant_id' => 'tenant-1'
            ]);

        $this->authenticateUserMock->expects($this->once())
            ->method('execute')
            ->with('test@example.com', 'password', 'tenant-1')
            ->willThrowException(new InvalidArgumentException('TenantId cannot be empty'));

        $response = $this->controller->login($this->requestMock, $this->authenticateUserMock);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(json_encode(['error' => 'TenantId cannot be empty']), $response->getContent());
    }

    public function testRegisterSuccess(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'id'        => 'user-1',
                'tenant_id' => 'tenant-1',
                'email'     => 'test@example.com',
                'password'  => 'password',
                'name'      => 'Test User',
            ]);

        $this->registerUserMock->expects($this->once())
            ->method('execute')
            ->with('user-1', 'tenant-1', 'test@example.com', 'password', 'Test User');

        $response = $this->controller->register($this->requestMock, $this->registerUserMock);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(json_encode(['message' => 'User registered successfully.']), $response->getContent());
    }

    public function testRegisterThrowsExceptionAndReturns400(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'id'        => 'user-1',
                'tenant_id' => 'tenant-1',
                'email'     => 'test@example.com',
                'password'  => 'password',
                'name'      => 'Test User',
            ]);

        $this->registerUserMock->expects($this->once())
            ->method('execute')
            ->with('user-1', 'tenant-1', 'test@example.com', 'password', 'Test User')
            ->willThrowException(new Exception('Registration failed.'));

        $response = $this->controller->register($this->requestMock, $this->registerUserMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(json_encode(['error' => 'Registration failed.']), $response->getContent());
    }

    public function testRegisterThrowsInvalidArgumentExceptionAndReturns400(): void
    {
        $this->requestMock->expects($this->once())
            ->method('validate')
            ->willReturn([
                'id'        => 'user-1',
                'tenant_id' => 'tenant-1',
                'email'     => 'test@example.com',
                'password'  => 'password',
                'name'      => 'Test User',
            ]);

        $this->registerUserMock->expects($this->once())
            ->method('execute')
            ->with('user-1', 'tenant-1', 'test@example.com', 'password', 'Test User')
            ->willThrowException(new InvalidArgumentException('TenantId cannot be empty'));

        $response = $this->controller->register($this->requestMock, $this->registerUserMock);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(json_encode(['error' => 'TenantId cannot be empty']), $response->getContent());
    }
}
