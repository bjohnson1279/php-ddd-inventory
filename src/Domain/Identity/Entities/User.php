<?php

namespace InventoryApp\Domain\Identity\Entities;

use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Identity\Events\UserRegistered;
use InventoryApp\Domain\Identity\Events\UserDeactivated;
use InventoryApp\Domain\Shared\Entities\AggregateRoot;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The User aggregate root in the Identity bounded context.
 *
 * Responsibilities:
 *  - Holds credentials (hashed password) and contact info
 *  - Belongs to exactly one Tenant (store)
 *  - Carries a set of Roles that determine what they can do
 *  - Provides canDo() for permission checks without exposing role internals
 */
class User extends AggregateRoot
{
    private string $id;
    private TenantId $tenantId;
    private string $email;
    private string $passwordHash;
    private string $name;
    private bool $active;
    /** @var Role[] */
    private array $roles;
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        TenantId $tenantId,
        string $email,
        string $passwordHash,
        string $name,
        array $roles = [],
        bool $active = true,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->id           = $id;
        $this->tenantId     = $tenantId;
        $this->email        = $email;
        $this->passwordHash = $passwordHash;
        $this->name         = $name;
        $this->roles        = $roles;
        $this->active       = $active;
        $this->createdAt    = $createdAt ?? new DateTimeImmutable();
    }

    public static function register(
        string $id,
        TenantId $tenantId,
        string $email,
        string $plainPassword,
        string $name
    ): self {
        $email = trim($email);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }
        if (strlen($plainPassword) < 8) {
            throw new InvalidArgumentException("Password must be at least 8 characters");
        }

        $user = new self(
            $id,
            $tenantId,
            strtolower(trim($email)),
            password_hash($plainPassword, PASSWORD_BCRYPT),
            trim($name),
            [Role::createDefault(Role::STAFF)] // default role
        );

        $user->recordEvent(new UserRegistered(
            userId:    $id,
            tenantId:  $tenantId,
            email:     strtolower(trim($email)),
            name:      trim($name),
            occurredOn: new DateTimeImmutable(),
        ));

        return $user;
    }

    public function getId(): string             { return $this->id; }
    public function getTenantId(): TenantId     { return $this->tenantId; }
    public function getEmail(): string          { return $this->email; }
    public function getPasswordHash(): string   { return $this->passwordHash; }
    public function getName(): string           { return $this->name; }
    public function isActive(): bool            { return $this->active; }
    public function getRoles(): array           { return $this->roles; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    /**
     * Check whether this user has a specific permission across any of their roles.
     */
    public function canDo(string $permission): bool
    {
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function assignRole(Role $role): void
    {
        foreach ($this->roles as $existing) {
            if ($existing->getId() === $role->getId()) {
                return; // already assigned — idempotent
            }
        }
        $this->roles[] = $role;
    }

    public function revokeRole(string $roleId): void
    {
        $this->roles = array_values(
            array_filter($this->roles, fn(Role $r) => $r->getId() !== $roleId)
        );
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->recordEvent(new UserDeactivated(
            userId:    $this->id,
            tenantId:  $this->tenantId,
            occurredOn: new DateTimeImmutable(),
        ));
    }

    public function reactivate(): void
    {
        $this->active = true;
    }
}
