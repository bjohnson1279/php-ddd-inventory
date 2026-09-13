<?php
declare(strict_types=1);

namespace InventoryApp\Domain\Receiving;

class Dimensions
{
    public function __construct(
        public readonly float $length,
        public readonly float $width,
        public readonly float $height
    ) {}
}

class InboundScan
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly ?string $purchaseOrderId,
        public readonly string $imageUrl,
        public readonly Dimensions $dimensions,
        public readonly float $anomalyScore,
        public readonly bool $hasDamage,
        public string $status,
        public readonly ?string $ocrText = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    public function approve(): void
    {
        if ($this->status !== 'PENDING') {
            throw new \DomainException("Cannot approve scan in status {$this->status}");
        }
        $this->status = 'APPROVED';
    }

    public function reject(): void
    {
        if ($this->status !== 'PENDING') {
            throw new \DomainException("Cannot reject scan in status {$this->status}");
        }
        $this->status = 'REJECTED';
    }
}
