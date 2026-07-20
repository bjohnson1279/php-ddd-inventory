<?php

namespace InventoryApp\Domain\Inventory\Repositories;

use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;

interface StockOnboardingRepositoryInterface
{
    public function save(StockOnboarding $onboarding): void;

    /** @throws \DomainException when not found */
    public function findOrFail(string $id): StockOnboarding;
}
