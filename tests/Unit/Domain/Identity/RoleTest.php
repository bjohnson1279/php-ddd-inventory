<?php

namespace Tests\Unit\Domain\Identity;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Identity\Entities\Role;
use InventoryApp\Domain\Identity\ValueObjects\Permission;
use InvalidArgumentException;

class RoleTest extends TestCase
{
    public function testAdminRoleHasAllPermissions(): void
    {
        $admin = Role::createDefault(Role::ADMIN);
        $this->assertTrue($admin->hasPermission(Permission::USERS_MANAGE));
        $this->assertTrue($admin->hasPermission(Permission::INTEGRATIONS_MANAGE));
        $this->assertTrue($admin->hasPermission(Permission::INVENTORY_RECEIVE));
    }

    public function testManagerRoleCannotManageUsers(): void
    {
        $manager = Role::createDefault(Role::MANAGER);
        $this->assertFalse($manager->hasPermission(Permission::USERS_MANAGE));
        $this->assertFalse($manager->hasPermission(Permission::INTEGRATIONS_MANAGE));
    }

    public function testStaffRoleIsReadAndSalesOnly(): void
    {
        $staff = Role::createDefault(Role::STAFF);
        $this->assertTrue($staff->hasPermission(Permission::SALES_PROCESS));
        $this->assertTrue($staff->hasPermission(Permission::INVENTORY_READ));
        $this->assertFalse($staff->hasPermission(Permission::INVENTORY_RECEIVE));
        $this->assertFalse($staff->hasPermission(Permission::CATALOG_MANAGE));
    }

    public function testUnknownRoleSlugThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Role::createDefault('superuser');
    }
}
