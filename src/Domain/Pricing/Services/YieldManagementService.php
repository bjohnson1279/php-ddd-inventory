<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Pricing\Services;

use InventoryApp\Domain\Pricing\Entities\LiquidationRule;
use InventoryApp\Domain\Pricing\Entities\MarkdownEvent;

interface YieldManagementRepositoryInterface
{
    /** @return LiquidationRule[] */
    public function getActiveRules(string $tenantId): array;
    public function saveMarkdownEvent(MarkdownEvent $event): void;
    public function updateVariantPrice(string $tenantId, string $variantId, int $newPriceCents): void;
    /** @return array<string, mixed> */
    public function getLotsExpiringWithin(string $tenantId, int $days): array;
}

class YieldManagementService
{
    private YieldManagementRepositoryInterface $repository;

    public function __construct(YieldManagementRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return MarkdownEvent[]
     */
    public function runYieldOptimization(string $tenantId): array
    {
        $rules = $this->repository->getActiveRules($tenantId);
        if (empty($rules)) {
            return [];
        }

        $events = [];
        // Implementation stub for parity. 
        // Real implementation fetches from repository, requests Python sidecar, and processes results.

        return $events;
    }
}
