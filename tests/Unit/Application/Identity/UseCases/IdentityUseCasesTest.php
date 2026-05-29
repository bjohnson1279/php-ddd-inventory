<?php

namespace Tests\Unit\Application\Identity\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Identity\UseCases\RegisterUser;
use InventoryApp\Application\Identity\UseCases\AssignRoleToUser;
use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\Entities\Role;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Identity\ValueObjects\Permission;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class IdentityUseCasesTest extends TestCase
{
    private function makeUser(string $id, string $roleSlug = Role::STAFF): User
    {
        $user = User::register($id, new TenantId('t1'), "{$id}@store.com", 'password123', 'Test User');
        if ($roleSlug !== Role::STAFF) {
            $user->assignRole(Role::createDefault($roleSlug));
        }
        return $user;
    }

    // ── RegisterUser ──────────────────────────────────────────────────────────

    public function testRegisterUserSavesNewUser(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('findByEmail')->willReturn(null);
        $repo->expects($this->once())->method('save')
            ->with($this->callback(fn(User $u) => $u->getEmail() === 'new@store.com'));

        (new RegisterUser($repo, $this->createStub(EventDispatcherInterface::class)))->execute('u1', 't1', 'new@store.com', 'password123', 'New User');
    }

    public function testRegisterUserThrowsWhenEmailAlreadyExists(): void
    {
        $existing = $this->makeUser('u-existing');
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($existing);
        $repo->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/already exists/i');
        (new RegisterUser($repo, $this->createStub(EventDispatcherInterface::class)))->execute('u2', 't1', 'new@store.com', 'password123', 'Dupe');
    }

    // ── AssignRoleToUser ──────────────────────────────────────────────────────

    public function testAssignRoleGrantsNewPermissionToTarget(): void
    {
        $admin  = $this->makeUser('admin-1', Role::ADMIN);
        $target = $this->makeUser('staff-1', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnMap([
                ['admin-1', $admin],
                ['staff-1', $target],
            ]);
        $repo->expects($this->once())->method('save')
            ->with($this->callback(fn(User $u) => $u->canDo(Permission::REPORTS_VIEW)));

        (new AssignRoleToUser($repo))->execute('staff-1', Role::MANAGER, 'admin-1');
    }

    public function testAssignRoleThrowsWhenActorLacksPermission(): void
    {
        $staff  = $this->makeUser('staff-actor', Role::STAFF);
        $target = $this->makeUser('staff-target', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['staff-actor', $staff],
            ['staff-target', $target],
        ]);
        $repo->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/unauthorized/i');
        (new AssignRoleToUser($repo))->execute('staff-target', Role::MANAGER, 'staff-actor');
    }

    public function testAssignRoleThrowsWhenTargetUserNotFound(): void
    {
        $admin = $this->makeUser('admin-1', Role::ADMIN);
        $repo  = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['admin-1', $admin],
            ['ghost',   null],
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/not found/i');
        (new AssignRoleToUser($repo))->execute('ghost', Role::MANAGER, 'admin-1');
    }
}
