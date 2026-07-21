<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

class InsufficientAvailableStockException extends \DomainException
{
    public function __construct(
        private readonly string $sku,
        private readonly int $requested,
        private readonly int $available
    ) {
        parent::__construct(sprintf(
            "Insufficient available stock (ATP) for SKU %s. Requested: %d, Available: %d",
            $sku,
            $requested,
            $available
        ));
    }

    public function getSku(): string { return $this->sku; }
    public function getRequested(): int { return $this->requested; }
    public function getAvailable(): int { return $this->available; }
}
