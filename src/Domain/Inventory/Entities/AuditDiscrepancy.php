<?php

namespace InventoryApp\Domain\Inventory\Entities;

class AuditDiscrepancy
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $type,
        public readonly string $referenceId,
        public readonly ?string $externalRefId,
        public readonly string $description,
        public string $status = 'OPEN',
        public ?\DateTimeImmutable $occurredAt = null,
        public ?\DateTimeImmutable $resolvedAt = null,
        public ?string $resolutionNotes = null
    ) {
        if ($this->occurredAt === null) {
            $this->occurredAt = new \DateTimeImmutable();
        }
    }

    public function resolve(string $notes): void
    {
        $this->status = 'RESOLVED';
        $this->resolvedAt = new \DateTimeImmutable();
        $this->resolutionNotes = $notes;
    }
}
