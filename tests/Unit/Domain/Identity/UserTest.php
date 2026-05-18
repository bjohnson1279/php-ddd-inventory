<?php

namespace Tests\Unit\Domain\Identity;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\Entities\Role;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Identity\ValueObjects\Permission;
use InvalidArgumentException;

class UserTest extends TestCase
{
    private function makeTenant(): TenantId
    {
        return new TenantId('tenant-123');
    }

    public function testCanRegisterUser(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        $this->assertEquals('jane@store.com', $user->getEmail());
        $this->assertEquals('Jane', $user->getName());
        $this->assertTrue($user->isActive());
    }

    public function testEmailIsNormalisedToLowercase(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'JANE@STORE.COM', 'password123', 'Jane');
        $this->assertEquals('jane@store.com', $user->getEmail());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::register('u1', $this->makeTenant(), 'not-an-email', 'password123', 'Jane');
    }

    public function testShortPasswordThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::register('u1', $this->makeTenant(), 'jane@store.com', 'short', 'Jane');
    }

    public function testNewUsersReceiveStaffRoleByDefault(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        $this->assertCount(1, $user->getRoles());
        $this->assertEquals(Role::STAFF, $user->getRoles()[0]->getId());
    }

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'mysecretpass', 'Jane');
        $this->assertTrue($user->verifyPassword('mysecretpass'));
    }

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'mysecretpass', 'Jane');
        $this->assertFalse($user->verifyPassword('wrongpassword'));
    }

    public function testCanDoReturnsTrueForPermissionGrantedByRole(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        $this->assertTrue($user->canDo(Permission::SALES_PROCESS));
    }

    public function testCanDoReturnsFalseForPermissionNotGranted(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        // Staff does not have USERS_MANAGE
        $this->assertFalse($user->canDo(Permission::USERS_MANAGE));
    }

    public function testAssignRoleGrantsNewPermissions(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        $this->assertFalse($user->canDo(Permission::USERS_MANAGE));

        $user->assignRole(Role::createDefault(Role::ADMIN));
        $this->assertTrue($user->canDo(Permission::USERS_MANAGE));
    }

    public function testAssignRoleIsIdempotent(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        $user->assignRole(Role::createDefault(Role::MANAGER));
        $user->assignRole(Role::createDefault(Role::MANAGER)); // second time

        $managerCount = count(array_filter($user->getRoles(), fn($r) => $r->getId() === Role::MANAGER));
        $this->assertEquals(1, $managerCount);
    }

    public function testRevokeRoleRemovesPermissions(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        $user->assignRole(Role::createDefault(Role::ADMIN));
        $user->revokeRole(Role::ADMIN);

        $this->assertFalse($user->canDo(Permission::USERS_MANAGE));
    }

    public function testDeactivatingUserSetsInactiveFlag(): void
    {
        $user = User::register('u1', $this->makeTenant(), 'jane@store.com', 'password123', 'Jane');
        $user->deactivate();
        $this->assertFalse($user->isActive());

        $user->reactivate();
        $this->assertTrue($user->isActive());
    }
}
