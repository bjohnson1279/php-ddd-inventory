<?php

namespace InventoryApp\Application\Identity\UseCases;

use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class RegisterUser
{
    public function __construct(
        private readonly UserRepositoryInterface  $userRepository,
        private readonly EventDispatcherInterface $events,
    ) {}

    public function execute(
        string $id,
        string $tenantIdValue,
        string $email,
        string $password,
        string $name,
        ?string $actingUserId = null
    ): void {
        if ($actingUserId !== null) {
            $actor = $this->userRepository->findById($actingUserId);
            if (!$actor || !$actor->canDo('users:manage')) {
                throw new Exception("Unauthorized: you do not have permission to manage users.");
            }

            if ($actor->getTenantId()->getValue() !== $tenantIdValue) {
                throw new Exception("Unauthorized: you cannot manage users in a different organization.");
            }
        }

        $tenantId = new TenantId($tenantIdValue);
        $email = strtolower(trim($email));

        if ($this->userRepository->findByEmail($email, $tenantId)) {
            throw new Exception("A user with email '{$email}' already exists for this tenant.");
        }

        $user = User::register($id, $tenantId, $email, $password, $name);
        $this->userRepository->save($user);

        foreach ($user->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
