<?php

namespace App\Domain\Aging;

class AgingBucket {
    public const DAYS_0_30 = '0_30_DAYS';
    public const DAYS_31_60 = '31_60_DAYS';
    public const DAYS_61_90 = '61_90_DAYS';
    public const DAYS_91_180 = '91_180_DAYS';
    public const OVER_180_DAYS = 'OVER_180_DAYS';
}

class RecommendedAction {
    public const NONE = 'NONE';
    public const MARKDOWN = 'MARKDOWN';
    public const LIQUIDATE = 'LIQUIDATE';
    public const DONATE = 'DONATE';
    public const SCRAP = 'SCRAP';
}

class DeadStockAnalysis {
    public string $sku;
    public string $locationId;
    public string $tenantId;
    public int $currentQuantity;
    public int $daysSinceLastMovement;
    public bool $isDeadStock;
    public string $agingBucket;
    public int $lockedCapitalCents;
    public string $recommendedAction;

    public function __construct(
        string $sku,
        string $locationId,
        string $tenantId,
        int $currentQuantity,
        int $daysSinceLastMovement,
        bool $isDeadStock,
        string $agingBucket,
        int $lockedCapitalCents,
        string $recommendedAction
    ) {
        $this->sku = $sku;
        $this->locationId = $locationId;
        $this->tenantId = $tenantId;
        $this->currentQuantity = $currentQuantity;
        $this->daysSinceLastMovement = $daysSinceLastMovement;
        $this->isDeadStock = $isDeadStock;
        $this->agingBucket = $agingBucket;
        $this->lockedCapitalCents = $lockedCapitalCents;
        $this->recommendedAction = $recommendedAction;
    }
}
