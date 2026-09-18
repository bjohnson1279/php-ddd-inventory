<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Pricing\Entities;

class LiquidationRule
{
    private string $id;
    private string $tenantId;
    private int $daysToExpiration;
    private float $markdownPercentage;
    private bool $isActive;
    private ?string $department;
    private ?string $sku;

    public function __construct(
        string $id,
        string $tenantId,
        int $daysToExpiration,
        float $markdownPercentage,
        bool $isActive = true,
        ?string $department = null,
        ?string $sku = null
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->daysToExpiration = $daysToExpiration;
        $this->markdownPercentage = $markdownPercentage;
        $this->isActive = $isActive;
        $this->department = $department;
        $this->sku = $sku;
    }
}
