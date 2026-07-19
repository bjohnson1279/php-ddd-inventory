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

    public function testRegisterReturns400OnException(): void
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
