<?php

namespace Tests\Unit\Application\Identity\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Identity\UseCases\RegisterUser;
use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\Entities\Role;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Identity\Events\UserRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;
use InvalidArgumentException;

class RegisterUserTest extends TestCase
{
    private function makeUser(string $id, string $roleSlug = Role::STAFF): User
    {
        $user = User::register($id, new TenantId('t1'), "{$id}@store.com", 'password123', 'Test User');
        if ($roleSlug !== Role::STAFF) {
            $user->assignRole(Role::createDefault($roleSlug));
        }
        return $user;
    }

    /**
     * @dataProvider validRegistrationProvider
     */
    public function testRegisterUserSavesNewUserAndDispatchesEvents(string $email, string $password, string $name, string $expectedEmail): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('findByEmail')->willReturn(null);
        $repo->expects($this->once())->method('save')
            ->with($this->callback(fn(User $u) => $u->getEmail() === $expectedEmail));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(UserRegistered::class));

        (new RegisterUser($repo, $dispatcher))->execute('u1', 't1', $email, $password, $name);
    }

    public function validRegistrationProvider(): array
    {
        return [
            'standard success' => ['new@store.com', 'password123', 'New User', 'new@store.com'],
            'plus addressing' => ['user+tag@store.com', 'password123', 'Plus User', 'user+tag@store.com'],
            'unicode email' => ['üser@store.com', 'password123', 'Unicode User', 'üser@store.com'],
            'mixed case email' => ['UsEr@StOrE.CoM', 'password123', 'Mixed Case User', 'user@store.com'],
            'special char password' => ['special@store.com', 'p@sswörd🔑123', 'Special Password User', 'special@store.com'],
            'long password' => ['long@store.com', str_repeat('a', 100), 'Long Password User', 'long@store.com'],
            'email with surrounding whitespace' => ['  spaced@store.com  ', 'password123', 'Spaced Email User', 'spaced@store.com'],
        ];
    }

    public function testRegisterUserThrowsWhenEmailAlreadyExists(): void
    {
        $existing = $this->makeUser('u-existing');
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($existing);
        $repo->expects($this->never())->method('save');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("A user with email 'new@store.com' already exists for this tenant.");
        (new RegisterUser($repo, $dispatcher))->execute('u2', 't1', 'new@store.com', 'password123', 'Dupe');
    }

    public function testRegisterUserThrowsWhenActorIsFromDifferentTenant(): void
    {
        $actor = $this->makeUser('actor-1', Role::ADMIN);
        // The actor is created with TenantId('t1') by default in makeUser

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['actor-1', $actor]
        ]);
        $repo->expects($this->never())->method('save');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unauthorized: you cannot manage users in a different organization.');
        (new RegisterUser($repo, $dispatcher))->execute('u3', 't2', 'new2@store.com', 'password123', 'Cross Tenant User', 'actor-1');
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testRegisterUserThrowsOnInvalidInput(string $email, string $password, string $tenantId, string $expectedException, string $expectedMessage): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        (new RegisterUser($repo, $dispatcher))->execute('u-invalid', $tenantId, $email, $password, 'New User');
    }

    public function invalidInputProvider(): array
    {
        return [
            'invalid email format' => [
                'invalid-email', 'password123', 't1', InvalidArgumentException::class, 'Invalid email address: invalid-email'
            ],
            'empty email' => [
                '', 'password123', 't1', InvalidArgumentException::class, 'Invalid email address: '
            ],
            'whitespace email' => [
                '   ', 'password123', 't1', InvalidArgumentException::class, 'Invalid email address: '
            ],
            'short password' => [
                'new@store.com', 'short', 't1', InvalidArgumentException::class, 'Password must be at least 8 characters'
            ],
            'empty password' => [
                'new@store.com', '', 't1', InvalidArgumentException::class, 'Password must be at least 8 characters'
            ],
            'empty tenant id' => [
                'new@store.com', 'password123', '', InvalidArgumentException::class, 'TenantId cannot be empty'
            ],
            'whitespace tenant id' => [
                'new@store.com', 'password123', '  ', InvalidArgumentException::class, 'TenantId cannot be empty'
            ],
            'malformed email domain' => [
                'user@store', 'password123', 't1', InvalidArgumentException::class, 'Invalid email address: user@store'
            ],
            'tenant id too long' => [
                'new@store.com', 'password123', str_repeat('a', 256), InvalidArgumentException::class, 'TenantId cannot exceed 255 characters'
            ],
        ];
    }

    public function testRegisterUserTrimsAndLowercasesEmailAndName(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('findByEmail')->willReturn(null);
        $repo->expects($this->once())->method('save')
            ->with($this->callback(function (User $u) {
                return $u->getEmail() === 'mixedcase@store.com' && $u->getName() === 'Spaced Name';
            }));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with($this->callback(function (UserRegistered $e) {
                return $e->email === 'mixedcase@store.com' && $e->name === 'Spaced Name';
            }));

        (new RegisterUser($repo, $dispatcher))->execute(
            'u6',
            't1',
            '  MIXEDcase@STORE.com   ',
            'password123',
            '   Spaced Name  '
        );
    }

    public function testRegisterUserPassesCorrectArgumentsToRepositoryWhenCheckingEmail(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
             ->method('findByEmail')
             ->with('check@store.com', $this->callback(fn(TenantId $t) => $t->getValue() === 't-check'))
             ->willReturn(null);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        (new RegisterUser($repo, $dispatcher))->execute('u7', 't-check', 'CHECK@store.com', 'password123', 'Check User');
    }

    public function testRegisterUserDoesNotDispatchEventsIfFindByEmailFails(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willThrowException(new \RuntimeException('Connection failed'));
        $repo->expects($this->never())->method('save');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection failed');

        (new RegisterUser($repo, $dispatcher))->execute('u-fail-find', 't1', 'fail@store.com', 'password123', 'Fail User');
    }

    public function testRegisterUserDoesNotDispatchEventsIfSaveFails(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null);
        $repo->method('save')->willThrowException(new \RuntimeException('Database error'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        (new RegisterUser($repo, $dispatcher))->execute('u8', 't1', 'fail@store.com', 'password123', 'Fail User');
    }

    public function testRegisterUserBubblesUpDispatcherExceptions(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null);
        $repo->expects($this->once())->method('save');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(new \RuntimeException('Dispatcher error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dispatcher error');

        (new RegisterUser($repo, $dispatcher))->execute('u-fail-dispatch', 't1', 'dispatch@store.com', 'password123', 'Dispatch User');
    }

    public function testRegisterUserSavesUserWithCorrectInitialState(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('findByEmail')->willReturn(null);

        $repo->expects($this->once())->method('save')->with($this->callback(function (User $user) {
            return $user->getId() === 'u-new'
                && $user->getTenantId()->getValue() === 't-new'
                && $user->getEmail() === 'state@store.com'
                && $user->getName() === 'State User'
                && $user->isActive() === true
                && count($user->getRoles()) === 1
                && $user->getRoles()[0]->getId() === Role::STAFF
                && $user->verifyPassword('mysecurepass123');
        }));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        (new RegisterUser($repo, $dispatcher))->execute('u-new', 't-new', 'state@store.com', 'mysecurepass123', 'State User');
    }
}
