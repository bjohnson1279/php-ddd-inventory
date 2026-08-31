<?php

namespace InventoryApp\Domain\Identity\ValueObjects;

use InvalidArgumentException;

/**
 * A fine-grained action string that can be granted to a Role.
 *
 * Permissions follow a  <context>:<action>  pattern so they are
 * human-readable and easy to group by bounded context.
 */
class Permission
{
    // Inventory operations
    public const INVENTORY_RECEIVE   = 'inventory:receive';
    public const INVENTORY_DISPATCH  = 'inventory:dispatch';
    public const INVENTORY_TRANSFER  = 'inventory:transfer';
    public const INVENTORY_RECONCILE = 'inventory:reconcile';
    public const INVENTORY_READ      = 'inventory:read';
    public const INVENTORY_ALLOCATE  = 'inventory:allocate';

    // POS / Sales operations
    public const SALES_PROCESS       = 'sales:process';
    public const RETURNS_PROCESS     = 'returns:process';
    public const RMA_CREATE          = 'rma:create';

    // Catalog management
    public const CATALOG_MANAGE      = 'catalog:manage';
    public const CATALOG_READ        = 'catalog:read';
    public const PRODUCT_MANAGE      = 'product:manage';

    // Reporting
    public const REPORTS_VIEW        = 'reports:view';

    // Integration configuration
    public const INTEGRATIONS_MANAGE = 'integrations:manage';

    // User / access management (admin only)
    public const USERS_MANAGE        = 'users:manage';

    private string $value;

    public function __construct(string $value)
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException("Permission cannot be empty");
        }
        $this->value = trim($value);
    }

    public function getValue(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
    public function __toString(): string { return $this->value; }
}
