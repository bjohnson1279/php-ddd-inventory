<?php

namespace App\Domain\YieldManagement;

class MarkdownStatus {
    public const PROPOSED = 'PROPOSED';
    public const APPROVED = 'APPROVED';
    public const PUSHED_TO_CHANNELS = 'PUSHED_TO_CHANNELS';
}

class LiquidationProfile {
    public string $sku;
    public int $basePriceCents;
    public int $holdingCostPerDayCents;
    public int $minFloorPriceCents;

    public function __construct(string $sku, int $basePriceCents, int $holdingCostPerDayCents, int $minFloorPriceCents) {
        $this->sku = $sku;
        $this->basePriceCents = $basePriceCents;
        $this->holdingCostPerDayCents = $holdingCostPerDayCents;
        $this->minFloorPriceCents = $minFloorPriceCents;
    }
}

class InventoryYieldMetrics {
    public string $sku;
    public int $daysInInventory;
    public ?int $daysUntilExpiration;
    public float $historicalDailyDemand;
    public int $currentStockQuantity;

    public function __construct(
        string $sku,
        int $daysInInventory,
        ?int $daysUntilExpiration,
        float $historicalDailyDemand,
        int $currentStockQuantity
    ) {
        $this->sku = $sku;
        $this->daysInInventory = $daysInInventory;
        $this->daysUntilExpiration = $daysUntilExpiration;
        $this->historicalDailyDemand = $historicalDailyDemand;
        $this->currentStockQuantity = $currentStockQuantity;
    }
}

class PriceMarkdownRecommendation {
    public string $id;
    public string $sku;
    public int $recommendedPriceCents;
    public string $reasoning;
    public string $status;

    public function __construct(
        string $id,
        string $sku,
        int $recommendedPriceCents,
        string $reasoning,
        string $status = MarkdownStatus::PROPOSED
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->recommendedPriceCents = $recommendedPriceCents;
        $this->reasoning = $reasoning;
        $this->status = $status;
    }

    public function approve(): void {
        if ($this->status !== MarkdownStatus::PROPOSED) {
            throw new \Exception("Can only approve PROPOSED markdowns");
        }
        $this->status = MarkdownStatus::APPROVED;
    }

    public function pushToChannels(): void {
        if ($this->status !== MarkdownStatus::APPROVED) {
            throw new \Exception("Can only push APPROVED markdowns");
        }
        $this->status = MarkdownStatus::PUSHED_TO_CHANNELS;
    }
}
