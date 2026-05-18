<?php

namespace InventoryApp\Domain\Identity\Repositories;

use InventoryApp\Domain\Identity\Entities\User;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email, TenantId $tenantId): ?User;
    public function save(User $user): void;
    public function delete(User $user): void;
}
