<?php

namespace InventoryApp\Domain\Catalog\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use DateTimeImmutable;

class VariantAddedToCatalog implements DomainEvent
{
    private string $productId;
    private string $productName;
    private Department $department;
    private SKU $sku;
    private DateTimeImmutable $occurredOn;

    public function __construct(string $productId, string $productName, Department $department, SKU $sku)
    {
        $this->productId = $productId;
        $this->productName = $productName;
        $this->department = $department;
        $this->sku = $sku;
        $this->occurredOn = new DateTimeImmutable();
    }

    public function getProductId(): string { return $this->productId; }
    public function getProductName(): string { return $this->productName; }
    public function getDepartment(): Department { return $this->department; }
    public function getSku(): SKU { return $this->sku; }
    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
