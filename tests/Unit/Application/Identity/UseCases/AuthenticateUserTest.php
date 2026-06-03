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

    /**
     * @dataProvider validAuthenticationProvider
     */
    public function testExecuteReturnsTokenOnSuccess(string $email, string $password, string $tenantId, string $expectedTrimmedTenantId): void
    {
        $user = $this->makeUser('u1', $email, $password);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($expectedTrimmedTenantId)))
            ->willReturn($user);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->once())
            ->method('issue')
            ->with('u1', $expectedTrimmedTenantId)
            ->willReturn('generated_token');

        $useCase = new AuthenticateUser($repo, $tokenService);
        $token = $useCase->execute($email, $password, $tenantId);

        $this->assertEquals('generated_token', $token);
    }

    public function validAuthenticationProvider(): array
    {
        return [
            'standard success' => ['user@store.com', 'password123', 't1', 't1'],
            'handles whitespace tenant id' => ['user@store.com', 'password123', '  t1  ', 't1'],
            'special character password' => ['user@store.com', 'p@sswörd🔑123', 't1', 't1'],
            'plus addressing email' => ['user+tag@store.com', 'password123', 't1', 't1'],
            'extremely long password' => ['user@store.com', str_repeat('a', 100), 't1', 't1'],
            'mixed casing email' => ['UsEr@StOrE.CoM', 'password123', 't1', 't1'],
            'unicode email' => ['üser@store.com', 'password123', 't1', 't1'],
        ];
    }

    /**
     * @dataProvider invalidCredentialsProvider
     */
    public function testExecuteThrowsOnInvalidCredentials(string $email, string $password, string $tenantId, bool $userExists, string $userEmail = 'user@store.com', string $userPassword = 'password123'): void
    {
        $mockedUser = $userExists ? $this->makeUser('u1', $userEmail, $userPassword) : null;

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($email, $this->equalTo(new TenantId($tenantId)))
            ->willReturn($mockedUser);

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $useCase->execute($email, $password, $tenantId);
    }

    public function invalidCredentialsProvider(): array
    {
        return [
            'user not found' => ['unknown@store.com', 'password123', 't1', false],
            'invalid password' => ['user@store.com', 'wrongpassword', 't1', true, 'user@store.com', 'password123'],
            'empty password' => ['user@store.com', '', 't1', true, 'user@store.com', 'password123'],
            'empty email' => ['', 'password123', 't1', false],
            'whitespace-only password' => ['user@store.com', '   ', 't1', true, 'user@store.com', 'password123'],
            'wrong password casing' => ['user@store.com', 'PASSWORD123', 't1', true, 'user@store.com', 'password123'],
            'malformed email' => ['user@store', 'password123', 't1', false],
            'null-byte password' => ['user@store.com', "password\0123", 't1', true, 'user@store.com', 'password123'],
        ];
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

    /**
     * @dataProvider invalidTenantIdProvider
     */
    public function testExecuteThrowsOnInvalidTenantId(string $tenantId, string $expectedMessage = 'TenantId cannot be empty'): void
    {
        $email = 'user@store.com';
        $password = 'password123';

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->never())->method('findByEmail');

        $tokenService = $this->createMock(ApiTokenService::class);
        $tokenService->expects($this->never())->method('issue');

        $useCase = new AuthenticateUser($repo, $tokenService);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $useCase->execute($email, $password, $tenantId);
    }

    public function invalidTenantIdProvider(): array
    {
        return [
            'empty tenant id' => [''],
            'null-byte tenant id' => ["\0"],
            'extremely long tenant id' => [str_repeat('a', 1000), 'TenantId cannot exceed 255 characters'],
            'whitespace string tenant id' => ['   '],
        ];
    }
}
