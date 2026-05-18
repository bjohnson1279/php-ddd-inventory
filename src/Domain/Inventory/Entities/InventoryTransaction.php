<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\TransactionType;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;
use DateTimeImmutable;

class InventoryTransaction
{
    private string $id;
    private string $productId;
    private TransactionType $type;
    private int $quantityChange; // Positive for addition, negative for deduction
    private Condition $condition;
    private DateTimeImmutable $createdAt;
    private ?string $reference; 

    public function __construct(
        string $id,
        string $productId,
        TransactionType $type,
        int $quantityChange,
        Condition $condition,
        DateTimeImmutable $createdAt,
        ?string $reference = null
    ) {
        $this->id = $id;
        $this->productId = $productId;
        $this->type = $type;
        $this->quantityChange = $quantityChange;
        $this->condition = $condition;
        $this->createdAt = $createdAt;
        $this->reference = $reference;
    }

    public function getId(): string { return $this->id; }
    public function getProductId(): string { return $this->productId; }
    public function getType(): TransactionType { return $this->type; }
    public function getQuantityChange(): int { return $this->quantityChange; }
    public function getCondition(): Condition { return $this->condition; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getReference(): ?string { return $this->reference; }
}
