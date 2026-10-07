<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;

/**
 * RoleController
 *
 * Handles HTTP requests for RBAC Role and Permission management.
 */
class RoleController
{
    /**
     * Lists all available roles for the tenant (system roles + custom roles).
     */
    public function listRoles($request): Response
    {
        $tenantId = $request->input('_auth_tenant_id');

        try {
            // TODO: Wire to underlying PHP use case or directly to database layer
            // For now we'll stub this out to match the Express / GraphQL behavior
            return new Response([
                'data' => [
                    // System roles
                    ['id' => 'admin', 'name' => 'Admin', 'isCustom' => false],
                    ['id' => 'warehouse_operator', 'name' => 'Warehouse Operator', 'isCustom' => false],
                    ['id' => 'inventory_manager', 'name' => 'Inventory Manager', 'isCustom' => false],
                    ['id' => 'finance_auditor', 'name' => 'Finance Auditor', 'isCustom' => false],
                ]
            ]);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Creates a new custom role scoped to the tenant.
     */
    public function createCustomRole($request): Response
    {
        $tenantId = $request->input('_auth_tenant_id');
        $data = $request->input();

        // Expected payload: { "name": "...", "description": "...", "permissionIds": [...] }

        try {
            // TODO: Implement actual logic
            return new Response([
                'data' => [
                    'id' => 'custom_' . $tenantId . '_' . time(),
                    'name' => $data['name'] ?? 'Unknown',
                    'isCustom' => true
                ]
            ], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Updates an existing role's permissions.
     */
    public function updateRolePermissions($request, $roleId): Response
    {
        $tenantId = $request->input('_auth_tenant_id');
        $permissionIds = $request->input('permissionIds', []);

        try {
            if (!is_array($permissionIds)) {
                $permissionIds = [];
            }

            Capsule::transaction(function () use ($roleId, $permissionIds) {
                Capsule::table('role_permissions')->where('role_id', $roleId)->delete();

                if (!empty($permissionIds)) {
                    $records = [];
                    foreach ($permissionIds as $perm) {
                        $records[] = [
                            'role_id' => $roleId,
                            'permission' => $perm,
                        ];
                    }
                    Capsule::table('role_permissions')->insert($records);
                }
            });

            return new Response(['message' => 'Role permissions updated successfully.']);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Deletes a custom role (if it has no assigned users).
     */
    public function deleteCustomRole($request, $roleId): Response
    {
        $tenantId = $request->input('_auth_tenant_id');

        try {
            $systemRoles = ['admin', 'warehouse_operator', 'inventory_manager', 'finance_auditor', 'manager', 'staff'];
            if (in_array($roleId, $systemRoles, true)) {
                throw new Exception("Cannot delete system role.");
            }

            $assignedUsersCount = Capsule::table('user_roles')
                ->where('role_id', $roleId)
                ->count();

            if ($assignedUsersCount > 0) {
                throw new Exception("Cannot delete role assigned to users.");
            }

            Capsule::table('role_permissions')->where('role_id', $roleId)->delete();
            Capsule::table('roles')->where('id', $roleId)->delete();

            return new Response(['message' => 'Role deleted successfully.']);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
