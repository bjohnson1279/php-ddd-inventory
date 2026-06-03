<?php

namespace Tests\Unit\Application\Identity\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Identity\UseCases\AssignRoleToUser;
use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\Entities\Role;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Identity\ValueObjects\Permission;
use Exception;

class AssignRoleToUserTest extends TestCase
{
    private function makeUser(string $id, string $roleSlug = Role::STAFF): User
    {
        $user = User::register($id, new TenantId('t1'), "{$id}@store.com", 'password123', 'Test User');
        if ($roleSlug !== Role::STAFF) {
            $user->assignRole(Role::createDefault($roleSlug));
        }
        return $user;
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

    public function testAssignRoleThrowsWhenActorNotFound(): void
    {
        $target = $this->makeUser('staff-target', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['ghost-actor', null],
            ['staff-target', $target],
        ]);
        $repo->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/unauthorized/i');
        (new AssignRoleToUser($repo))->execute('staff-target', Role::MANAGER, 'ghost-actor');
    }

    public function testAssignRoleThrowsWhenRoleIsInvalid(): void
    {
        $admin  = $this->makeUser('admin-1', Role::ADMIN);
        $target = $this->makeUser('staff-1', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['admin-1', $admin],
            ['staff-1', $target],
        ]);
        $repo->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown default role/i');
        (new AssignRoleToUser($repo))->execute('staff-1', 'nonexistent-role', 'admin-1');
    }

    public function testAssignRoleIsIdempotent(): void
    {
        $admin  = $this->makeUser('admin-1', Role::ADMIN);
        $target = $this->makeUser('staff-1', Role::STAFF);

        $target->assignRole(Role::createDefault(Role::MANAGER));
        $rolesCount = count($target->getRoles());

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['admin-1', $admin],
            ['staff-1', $target],
        ]);

        $repo->expects($this->once())->method('save')
            ->with($this->callback(function (User $u) use ($rolesCount) {
                return count($u->getRoles()) === $rolesCount && $u->canDo(Permission::REPORTS_VIEW);
            }));

        (new AssignRoleToUser($repo))->execute('staff-1', Role::MANAGER, 'admin-1');
    }

    public function testActorCanAssignRoleToThemselves(): void
    {
        $admin = $this->makeUser('admin-1', Role::ADMIN);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['admin-1', $admin],
        ]);

        $repo->expects($this->once())->method('save')
            ->with($this->callback(function (User $u) {
                // Ensure they have the original ADMIN role and the newly assigned MANAGER role
                $hasManager = false;
                foreach ($u->getRoles() as $r) {
                    if ($r->getId() === Role::MANAGER) {
                        $hasManager = true;
                        break;
                    }
                }
                return $hasManager && $u->canDo(Permission::REPORTS_VIEW);
            }));

        (new AssignRoleToUser($repo))->execute('admin-1', Role::MANAGER, 'admin-1');
    }
}
