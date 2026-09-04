<?php

namespace InventoryApp\Domain\Approval;

use DomainException;
use DateTimeInterface;
use DateTimeImmutable;

class ApprovalDecisionRecord
{
    public function __construct(
        public readonly string $id,
        public readonly int $stepIndex,
        public readonly string $deciderId,
        public readonly string $decision,
        public readonly ?string $notes = null,
        public readonly ?DateTimeInterface $decidedAt = null
    ) {
    }
}
