<?php

namespace InventoryApp\Application\Identity\UseCases;

use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Infrastructure\Identity\ApiTokenService;
use Exception;

class AuthenticateUser
{
    private UserRepositoryInterface $userRepository;
    private ApiTokenService $tokenService;

    public function __construct(UserRepositoryInterface $userRepository, ApiTokenService $tokenService)
    {
        $this->userRepository = $userRepository;
        $this->tokenService   = $tokenService;
    }

    /**
     * Verify credentials and return a signed API token on success.
     *
     * @return string  The bearer token to include in subsequent requests
     * @throws Exception on invalid credentials or inactive account
     */
    public function execute(string $email, string $password, string $tenantIdValue): string
    {
        $tenantId = new TenantId($tenantIdValue);
        $user     = $this->userRepository->findByEmail($email, $tenantId);

        if (!$user) {
            throw new Exception("Invalid credentials.");
        }
        if (!$user->isActive()) {
            throw new Exception("Account is deactivated. Contact your administrator.");
        }
        if (!$user->verifyPassword($password)) {
            throw new Exception("Invalid credentials.");
        }

        return $this->tokenService->issue($user->getId(), $user->getTenantId()->getValue());
    }
}
