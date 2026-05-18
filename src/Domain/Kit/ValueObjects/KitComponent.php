<?php

namespace InventoryApp\Domain\Kit\ValueObjects;

final class KitComponent
{
    public function __construct(
        public readonly string $variantId,
        public readonly int $quantity,
    ) {
        if ($this->quantity < 1) {
            throw new \InvalidArgumentException('Kit component quantity must be at least 1.');
        }
    }
}
