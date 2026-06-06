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
        static $cachedPasswordHash = null;
        if ($cachedPasswordHash === null) {
            $cachedPasswordHash = password_hash('password123', PASSWORD_ARGON2ID);
        }
        $user = new User($id, new TenantId('t1'), "{$id}@store.com", $cachedPasswordHash, 'Test User', [Role::createDefault(Role::STAFF)]);
        if ($roleSlug !== Role::STAFF) {
            $user->assignRole(Role::createDefault($roleSlug));
        }
        return $user;
    }

    // ── AssignRoleToUser ──────────────────────────────────────────────────────

    /**
     * @dataProvider roleAssignmentProvider
     */
    public function testAssignRoleGrantsNewPermissionToTarget(string $roleToAssign, string $expectedPermission): void
    {
        $admin  = $this->makeUser('admin-1', Role::ADMIN);
        $target = $this->makeUser('target-1', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')
            ->willReturnMap([
                ['admin-1', $admin],
                ['target-1', $target],
            ]);
        $repo->expects($this->once())->method('save')
            ->with($this->callback(function (User $u) use ($expectedPermission, $roleToAssign) {
                // Ensure the assigned role is actually in the user's roles
                $hasRole = false;
                foreach ($u->getRoles() as $r) {
                    if ($r->getId() === $roleToAssign) {
                        $hasRole = true;
                        break;
                    }
                }
                $expectedRoleCount = $roleToAssign === Role::STAFF ? 1 : 2;
                return $hasRole
                    && $u->canDo($expectedPermission)
                    && $u->getId() === 'target-1'
                    && count($u->getRoles()) === $expectedRoleCount; // initial STAFF + the new role, or just 1 if assigning STAFF again
            }));

        (new AssignRoleToUser($repo))->execute('target-1', $roleToAssign, 'admin-1');
    }

    public function roleAssignmentProvider(): array
    {
        return [
            'assign manager role' => [Role::MANAGER, Permission::REPORTS_VIEW],
            'assign admin role'   => [Role::ADMIN, Permission::USERS_MANAGE],
            'assign staff role'   => [Role::STAFF, Permission::SALES_PROCESS],
        ];
    }

    public function testActorWithCustomRoleHavingUsersManagePermissionCanAssignRoles(): void
    {
        $customRole = new Role('custom-admin', 'Custom Admin', [Permission::USERS_MANAGE]);
        $actor  = $this->makeUser('actor-custom', Role::STAFF);
        // Overwrite roles with the custom one
        $actor->revokeRole(Role::STAFF);
        $actor->assignRole($customRole);

        $target = $this->makeUser('target-user', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['actor-custom', $actor],
            ['target-user', $target],
        ]);

        $repo->expects($this->once())->method('save')
            ->with($this->callback(function (User $u) {
                return $u->getId() === 'target-user' && count($u->getRoles()) === 2 && $u->canDo(Permission::REPORTS_VIEW);
            }));

        (new AssignRoleToUser($repo))->execute('target-user', Role::MANAGER, 'actor-custom');
    }

    /**
     * @dataProvider unauthorizedRolesProvider
     */
    public function testAssignRoleThrowsWhenActorLacksPermission(string $actorRole): void
    {
        $actor  = $this->makeUser('actor', $actorRole);
        $target = $this->makeUser('target', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['actor', $actor],
            ['target', $target],
        ]);
        $repo->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unauthorized: you do not have permission to manage users.');
        (new AssignRoleToUser($repo))->execute('target', Role::MANAGER, 'actor');
    }

    public function unauthorizedRolesProvider(): array
    {
        return [
            'staff role' => [Role::STAFF],
            'manager role' => [Role::MANAGER],
        ];
    }

    public function testAssignRolePreservesExistingRoles(): void
    {
        $admin = $this->makeUser('admin-1', Role::ADMIN);

        // Target user initially only has STAFF role.
        // By default, the registered user gets the STAFF role.
        $target = $this->makeUser('target-staff');

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['admin-1', $admin],
            ['target-staff', $target],
        ]);

        $repo->expects($this->once())->method('save')
            ->with($this->callback(function (User $u) {
                return $u->getId() === 'target-staff'
                    && count($u->getRoles()) === 2
                    && $u->getRoles()[0]->getId() === Role::STAFF
                    && $u->getRoles()[1]->getId() === Role::MANAGER;
            }));

        (new AssignRoleToUser($repo))->execute('target-staff', Role::MANAGER, 'admin-1');
    }

    public function testAssignRoleThrowsWhenTargetUserNotFound(): void
    {
        $admin = $this->makeUser('admin-1', Role::ADMIN);
        $repo  = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['admin-1', $admin],
            ['ghost',   null],
        ]);
        $repo->expects($this->never())->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User not found: ghost');
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
        $this->expectExceptionMessage('Unauthorized: you do not have permission to manage users.');
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
        $this->expectExceptionMessage("Unknown default role: nonexistent-role");
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
                return count($u->getRoles()) === $rolesCount
                    && $u->canDo(Permission::REPORTS_VIEW)
                    && $u->getId() === 'staff-1';
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
                return $hasManager
                    && $u->canDo(Permission::REPORTS_VIEW)
                    && $u->getId() === 'admin-1'
                    && count($u->getRoles()) === 3; // STAFF + ADMIN + MANAGER
            }));

        (new AssignRoleToUser($repo))->execute('admin-1', Role::MANAGER, 'admin-1');
    }

    public function testAssignRoleThrowsWhenRepositorySaveFails(): void
    {
        $admin  = $this->makeUser('admin-1', Role::ADMIN);
        $target = $this->makeUser('target-1', Role::STAFF);

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findById')->willReturnMap([
            ['admin-1', $admin],
            ['target-1', $target],
        ]);

        $repo->expects($this->once())->method('save')->willThrowException(new Exception('Database error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database error');

        (new AssignRoleToUser($repo))->execute('target-1', Role::MANAGER, 'admin-1');
    }
}
