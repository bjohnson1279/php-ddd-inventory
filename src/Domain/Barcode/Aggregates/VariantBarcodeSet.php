<?php

namespace InventoryApp\Domain\Barcode\Aggregates;

use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSource;

final class BarcodeAssignment
{
    public function __construct(public readonly string $id, public readonly string $variantId, public readonly Barcode $barcode, public readonly BarcodeSource $source, public readonly bool $isPrimary, public readonly \DateTimeImmutable $assignedAt) {}
}

class VariantBarcodeSet
{
    private array $assignments = [];
    private array $domainEvents = [];
    public function __construct(public readonly string $variantId) {}

    public function assign(Barcode $barcode, BarcodeSource $source, bool $makePrimary = false): BarcodeAssignment
    {
        foreach ($this->assignments as $existing) {
            if ($existing->barcode->equals($barcode)) {
                throw new \DomainException('Barcode already assigned to this variant');
            }
        }

        if ($makePrimary) {
            foreach ($this->assignments as $i => $a) {
                if ($a->isPrimary) {
                    $this->assignments[$i] = new BarcodeAssignment($a->id, $a->variantId, $a->barcode, $a->source, false, $a->assignedAt);
                }
            }
        }

        $shouldBePrimary = $makePrimary || empty($this->assignments);
        $assignment = new BarcodeAssignment(\Ramsey\Uuid\Uuid::uuid4()->toString(), $this->variantId, $barcode, $source, $shouldBePrimary, new \DateTimeImmutable());
        $this->assignments[$assignment->id] = $assignment;
        $this->domainEvents[] = new \stdClass();
        return $assignment;
    }

    public function revoke(string $assignmentId): void
    {
        $assignment = $this->assignments[$assignmentId] ?? null;
        if ($assignment === null) throw new \DomainException('Assignment not found');
        if ($assignment->isPrimary && count($this->assignments) > 1) throw new \DomainException('Cannot revoke primary while others exist');
        unset($this->assignments[$assignmentId]);
        $this->domainEvents[] = new \stdClass();
    }

    public function primaryBarcode(): ?BarcodeAssignment
    {
        foreach ($this->assignments as $a) if ($a->isPrimary) return $a;
        return null;
    }

    public function all(): array { return array_values($this->assignments); }
    public function releaseEvents(): array { $e = $this->domainEvents; $this->domainEvents = []; return $e; }
}
