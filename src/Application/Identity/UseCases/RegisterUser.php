<?php

namespace InventoryApp\Application\Identity\UseCases;

use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use Exception;

class RegisterUser
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function execute(
        string $id,
        string $tenantIdValue,
        string $email,
        string $password,
        string $name
    ): void {
        $tenantId = new TenantId($tenantIdValue);

        if ($this->userRepository->findByEmail($email, $tenantId)) {
            throw new Exception("A user with email '{$email}' already exists for this tenant.");
        }

        $user = User::register($id, $tenantId, $email, $password, $name);
        $this->userRepository->save($user);
    }
}
