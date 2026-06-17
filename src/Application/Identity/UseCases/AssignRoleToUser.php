<?php

namespace InventoryApp\Application\Identity\UseCases;

use InventoryApp\Domain\Identity\Entities\Role;
use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use Exception;

class AssignRoleToUser
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function execute(string $targetUserId, string $roleSlug, string $actingUserId): void
    {
        $actor = $this->userRepository->findById($actingUserId);
        if (!$actor || !$actor->canDo('users:manage')) {
            throw new Exception("Unauthorized: you do not have permission to manage users.");
        }

        $target = $this->userRepository->findById($targetUserId);
        if (!$target) {
            throw new Exception("User not found: {$targetUserId}");
        }

        if ($actor->getTenantId()->getValue() !== $target->getTenantId()->getValue()) {
            throw new Exception("Unauthorized: cross-tenant role assignment is not allowed.");
        }

        $role = Role::createDefault($roleSlug);
        $target->assignRole($role);
        $this->userRepository->save($target);
    }
}
