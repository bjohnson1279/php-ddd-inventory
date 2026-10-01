<?php

namespace App\Domain\CycleCount;

class CycleCountLineItem
{
    private string $id;
    private string $cycleCountId;
    private string $sku;
    private int $expectedQuantity;
    private ?int $countedQuantity;
    private ?int $varianceQuantity;
    private string $status;

    public function __construct(string $id, string $cycleCountId, string $sku, int $expectedQuantity, string $status = 'PENDING')
    {
        $this->id = $id;
        $this->cycleCountId = $cycleCountId;
        $this->sku = $sku;
        $this->expectedQuantity = $expectedQuantity;
        $this->status = $status;
        $this->countedQuantity = null;
        $this->varianceQuantity = null;
    }

    public function getSku(): string { return $this->sku; }
    public function getExpectedQuantity(): int { return $this->expectedQuantity; }
    public function getCountedQuantity(): ?int { return $this->countedQuantity; }
    public function getVarianceQuantity(): ?int { return $this->varianceQuantity; }
    public function getStatus(): string { return $this->status; }

    public function recordCount(int $counted, float $varianceThresholdPct = 0.05): bool
    {
        $this->countedQuantity = $counted;
        $this->varianceQuantity = $counted - $this->expectedQuantity;

        $variancePct = $this->expectedQuantity === 0 
            ? ($counted === 0 ? 0.0 : 1.0) 
            : abs($this->varianceQuantity) / $this->expectedQuantity;

        if ($variancePct > $varianceThresholdPct) {
            $this->status = 'VARIANCE_FLAGGED';
            return false;
        } else {
            $this->status = 'MATCHED';
            return true;
        }
    }
}
