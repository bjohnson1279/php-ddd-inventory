<?php

namespace Tests\Unit\Application\Identity\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Identity\UseCases\AuthenticateUser;
use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Infrastructure\Identity\ApiTokenService;
use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InvalidArgumentException;
use Exception;

class AuthenticateUserTest extends TestCase
{
    private function makeUser(string $id, string $email, string $password, bool $active = true): User
    {
        $user = User::register($id, new TenantId('t1'), $email, $password, 'Test User');
        if (!$active) {
            $user->deactivate();
        }
        return $user;
    }

    public function testExecuteReturnsTokenOnSuccess(): void
    {
        $email = 'user@store.com';
        $password = 'password123';
        $tenantId = 't1';
        $user = $this->makeUser('u1', $email, $password);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($tenantId)))
            ->willReturn($user);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->once())
            ->method('issue')
            ->with('u1', $tenantId)
            ->willReturn('generated_token');

        $useCase = new AuthenticateUser($repo, $tokenService);
        $token = $useCase->execute($email, $password, $tenantId);

        $this->assertEquals('generated_token', $token);
    }

    public function testExecuteThrowsWhenUserNotFound(): void
    {
        $email = 'unknown@store.com';
        $password = 'password123';
        $tenantId = 't1';

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($tenantId)))
            ->willReturn(null);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $useCase->execute($email, $password, $tenantId);
    }

    public function testExecuteThrowsWhenUserIsInactive(): void
    {
        $email = 'user@store.com';
        $password = 'password123';
        $tenantId = 't1';
        $user = $this->makeUser('u1', $email, $password, false);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($tenantId)))
            ->willReturn($user);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Account is deactivated. Contact your administrator.');

        $useCase->execute($email, $password, $tenantId);
    }

    public function testExecuteThrowsWhenPasswordIsInvalid(): void
    {
        $email = 'user@store.com';
        $password = 'password123';
        $wrongPassword = 'wrongpassword';
        $tenantId = 't1';
        $user = $this->makeUser('u1', $email, $password);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($tenantId)))
            ->willReturn($user);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $useCase->execute($email, $wrongPassword, $tenantId);
    }

    public function testExecuteThrowsWhenTokenServiceFails(): void
    {
        $email = 'user@store.com';
        $password = 'password123';
        $tenantId = 't1';
        $user = $this->makeUser('u1', $email, $password);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($tenantId)))
            ->willReturn($user);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->once())
            ->method('issue')
            ->willThrowException(new Exception('Token generation failed'));

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Token generation failed');

        $useCase->execute($email, $password, $tenantId);
    }

    public function testExecuteThrowsWhenRepositoryFails(): void
    {
        $email = 'user@store.com';
        $password = 'password123';
        $tenantId = 't1';

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->willThrowException(new Exception('Database connection failed'));

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())
            ->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database connection failed');

        $useCase->execute($email, $password, $tenantId);
    }

    public function testExecuteThrowsOnEmptyTenantId(): void
    {
        $email = 'user@store.com';
        $password = 'password123';
        $tenantId = '';

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->never())->method('findByEmail');

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TenantId cannot be empty');

        $useCase->execute($email, $password, $tenantId);
    }

    public function testExecuteHandlesWhitespaceTenantId(): void
    {
        $email = 'user@store.com';
        $password = 'password123';
        $tenantId = '  t1  ';
        $trimmedTenantId = 't1';
        $user = $this->makeUser('u1', $email, $password);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($trimmedTenantId)))
            ->willReturn($user);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->once())
            ->method('issue')
            ->with('u1', $trimmedTenantId)
            ->willReturn('generated_token');

        $useCase = new AuthenticateUser($repo, $tokenService);
        $token = $useCase->execute($email, $password, $tenantId);

        $this->assertEquals('generated_token', $token);
    }

    public function testExecuteThrowsOnEmptyPassword(): void
    {
        $email = 'user@store.com';
        $password = '';
        $tenantId = 't1';
        $user = $this->makeUser('u1', $email, 'password123');

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($tenantId)))
            ->willReturn($user);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $useCase->execute($email, $password, $tenantId);
    }
}
