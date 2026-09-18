<?php

declare(strict_types=1);

namespace InventoryApp\Domain\Pricing\Entities;

class MarkdownEvent
{
    private string $id;
    private string $tenantId;
    private string $variantId;
    private int $originalPriceCents;
    private int $newPriceCents;
    private string $reason;
    private \DateTimeImmutable $createdAt;
    private ?string $ruleId;

    public function __construct(
        string $id,
        string $tenantId,
        string $variantId,
        int $originalPriceCents,
        int $newPriceCents,
        string $reason,
        \DateTimeImmutable $createdAt,
        ?string $ruleId = null
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->variantId = $variantId;
        $this->originalPriceCents = $originalPriceCents;
        $this->newPriceCents = $newPriceCents;
        $this->reason = $reason;
        $this->createdAt = $createdAt;
        $this->ruleId = $ruleId;
    }
}
