<?php

namespace InventoryApp\Domain\Catalog\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Shared\ValueObjects\Money;

class Variant
{
    private string $id;
    private string $productId;
    private SKU $sku;
    private array $attributes; // e.g. ['Size' => 'M', 'Color' => 'Red']
    private Money $price;

    public function __construct(
        string $id,
        string $productId,
        SKU $sku,
        array $attributes,
        Money|float $price = 0.0
    ) {
        $this->id = $id;
        $this->productId = $productId;
        $this->sku = $sku;
        $this->attributes = $attributes;
        $this->price = $price instanceof Money ? $price : Money::fromDollars($price);
    }

    public function getId(): string { return $this->id; }
    public function getProductId(): string { return $this->productId; }
    public function getSku(): SKU { return $this->sku; }
    public function getAttributes(): array { return $this->attributes; }
    public function getPrice(): float { return $this->price->getAmountInDollars(); }
    public function getPriceMoney(): Money { return $this->price; }
}
