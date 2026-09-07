<?php

namespace App\Domain\Supplier;

class ASN
{
    private string $id;
    private string $tenantId;
    private string $poId;
    private string $supplierId;
    private \DateTimeImmutable $expectedArrivalDate;
    private string $status;
    private array $lines;

    public function __construct(
        string $id,
        string $tenantId,
        string $poId,
        string $supplierId,
        \DateTimeImmutable $expectedArrivalDate,
        string $status,
        array $lines
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->poId = $poId;
        $this->supplierId = $supplierId;
        $this->expectedArrivalDate = $expectedArrivalDate;
        $this->status = $status;
        $this->lines = $lines;
    }

    public function getId(): string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getPoId(): string { return $this->poId; }
    public function getSupplierId(): string { return $this->supplierId; }
    public function getExpectedArrivalDate(): \DateTimeImmutable { return $this->expectedArrivalDate; }
    public function getStatus(): string { return $this->status; }
    public function getLines(): array { return $this->lines; }
}
