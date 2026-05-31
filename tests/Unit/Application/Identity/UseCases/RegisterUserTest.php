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

    public function testRegisterUserSavesNewUserAndDispatchesEvents(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('findByEmail')->willReturn(null);
        $repo->expects($this->once())->method('save')
            ->with($this->callback(fn(User $u) => $u->getEmail() === 'new@store.com'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(UserRegistered::class));

        (new RegisterUser($repo, $dispatcher))->execute('u1', 't1', 'new@store.com', 'password123', 'New User');
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
        $this->expectExceptionMessageMatches('/already exists/i');
        (new RegisterUser($repo, $dispatcher))->execute('u2', 't1', 'new@store.com', 'password123', 'Dupe');
    }

    public function testRegisterUserThrowsWhenEmailIsInvalid(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null); // The repository is checked before the User is instantiated

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalid email address/i');

        // This will throw when User::register is called inside RegisterUser->execute
        (new RegisterUser($repo, $dispatcher))->execute('u3', 't1', 'invalid-email', 'password123', 'New User');
    }

    public function testRegisterUserThrowsWhenPasswordIsTooShort(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null); // The repository is checked before the User is instantiated

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/at least 8 characters/i');

        // This will throw when User::register is called inside RegisterUser->execute
        (new RegisterUser($repo, $dispatcher))->execute('u4', 't1', 'new@store.com', 'short', 'New User');
    }
}
