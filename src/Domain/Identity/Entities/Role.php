<?php

namespace InventoryApp\Domain\Identity\Entities;

use InventoryApp\Domain\Identity\ValueObjects\Permission;
use InvalidArgumentException;

/**
 * A named set of permissions that can be assigned to Users.
 *
 * Pre-defined roles:
 *  - ADMIN   : full access including user management
 *  - MANAGER : can manage inventory, catalog, and view reports
 *  - STAFF   : can process sales and returns, read-only inventory
 */
class Role
{
    public const ADMIN   = 'admin';
    public const MANAGER = 'manager';
    public const STAFF   = 'staff';

    /** @var array<string, string[]> role => permission constants */
    private const DEFAULT_PERMISSIONS = [
        self::ADMIN => [
            Permission::INVENTORY_RECEIVE,
            Permission::INVENTORY_DISPATCH,
            Permission::INVENTORY_TRANSFER,
            Permission::INVENTORY_RECONCILE,
            Permission::INVENTORY_READ,
            Permission::SALES_PROCESS,
            Permission::RETURNS_PROCESS,
            Permission::CATALOG_MANAGE,
            Permission::CATALOG_READ,
            Permission::REPORTS_VIEW,
            Permission::INTEGRATIONS_MANAGE,
            Permission::USERS_MANAGE,
        ],
        self::MANAGER => [
            Permission::INVENTORY_RECEIVE,
            Permission::INVENTORY_DISPATCH,
            Permission::INVENTORY_TRANSFER,
            Permission::INVENTORY_RECONCILE,
            Permission::INVENTORY_READ,
            Permission::SALES_PROCESS,
            Permission::RETURNS_PROCESS,
            Permission::CATALOG_MANAGE,
            Permission::CATALOG_READ,
            Permission::REPORTS_VIEW,
        ],
        self::STAFF => [
            Permission::INVENTORY_READ,
            Permission::SALES_PROCESS,
            Permission::RETURNS_PROCESS,
            Permission::CATALOG_READ,
        ],
    ];

    private string $id;
    private string $name;
    /** @var string[] */
    private array $permissions;

    public function __construct(string $id, string $name, array $permissions = [])
    {
        $this->id          = $id;
        $this->name        = $name;
        $this->permissions = $permissions;
    }

    public static function createDefault(string $slug): self
    {
        if (!isset(self::DEFAULT_PERMISSIONS[$slug])) {
            throw new InvalidArgumentException("Unknown default role: {$slug}");
        }
        return new self(
            $slug,
            ucfirst($slug),
            self::DEFAULT_PERMISSIONS[$slug]
        );
    }

    public function getId(): string   { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getPermissions(): array { return $this->permissions; }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
