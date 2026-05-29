<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\Entities\Role;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Infrastructure\Models\UserModel;
use DateTimeImmutable;

/**
 * Postgres-backed implementation of UserRepositoryInterface.
 *
 * Role hydration strategy:
 *  - On LOAD  : eager-load user_roles pivot → roles table → call Role::createDefault($slug)
 *  - On SAVE  : delete existing user_roles rows then reinsert from User::getRoles()
 *    This is safe because role sets are small (≤3 rows per user) and the operation
 *    is not high-frequency.
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?User
    {
        $model = UserModel::with('userRoles')->find($id);
        return $model ? $this->hydrate($model) : null;
    }

    public function findByEmail(string $email, TenantId $tenantId): ?User
    {
        $model = UserModel::with('userRoles')
            ->where('tenant_id', $tenantId->getValue())
            ->where('email', strtolower(trim($email)))
            ->first();

        return $model ? $this->hydrate($model) : null;
    }

    public function save(User $user): void
    {
        UserModel::updateOrCreate(
            ['id' => $user->getId()],
            [
                'tenant_id'     => $user->getTenantId()->getValue(),
                'email'         => $user->getEmail(),
                'password_hash' => $user->getPasswordHash(),
                'name'          => $user->getName(),
                'active'        => $user->isActive(),
                'created_at'    => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ]
        );

        // Sync roles: delete-then-reinsert is safe for small role sets
        \Illuminate\Database\Capsule\Manager::table('user_roles')
            ->where('user_id', $user->getId())
            ->delete();

        foreach ($user->getRoles() as $role) {
            \Illuminate\Database\Capsule\Manager::table('user_roles')->insert([
                'user_id' => $user->getId(),
                'role_id' => $role->getId(),
            ]);
        }
    }

    public function delete(User $user): void
    {
        UserModel::where('id', $user->getId())->delete();
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function hydrate(UserModel $model): User
    {
        $roles = $model->userRoles
            ->map(function ($roleModel) {
                try {
                    return Role::createDefault($roleModel->id);
                } catch (\InvalidArgumentException) {
                    // Unknown role slug in DB — skip gracefully
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();

        return new User(
            id:           $model->id,
            tenantId:     new TenantId($model->tenant_id),
            email:        $model->email,
            passwordHash: $model->password_hash,
            name:         $model->name,
            roles:        $roles,
            active:       (bool) $model->active,
            createdAt:    new DateTimeImmutable($model->created_at),
        );
    }
}
