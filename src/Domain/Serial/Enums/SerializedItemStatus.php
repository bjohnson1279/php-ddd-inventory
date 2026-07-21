<?php

namespace InventoryApp\Domain\Serial\Enums;

enum SerializedItemStatus: string
{
    case Pending = 'pending';
    case InStock = 'in_stock';
    case Sold = 'sold';
    case Returned = 'returned';
    case Quarantined = 'quarantined';
    case Transferred = 'transferred';
    case Damaged = 'damaged';
    case WrittenOff = 'written_off';

    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending => [self::InStock, self::Damaged, self::Quarantined],
            self::InStock => [self::Sold, self::Damaged, self::Quarantined, self::Transferred, self::WrittenOff],
            self::Sold => [self::Returned],
            self::Returned => [self::InStock, self::Damaged, self::WrittenOff, self::Quarantined],
            self::Quarantined => [self::InStock, self::Damaged, self::WrittenOff],
            self::Transferred => [self::InStock, self::Damaged],
            self::Damaged => [self::Quarantined, self::WrittenOff],
            self::WrittenOff => [],
        };
    }

    public function canTransitionTo(SerializedItemStatus $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isCountedInStock(): bool { return $this === self::InStock; }

    public function requiresLedgerEntry(SerializedItemStatus $from): bool
    {
        $enteringStock = $this === self::InStock && !$from->isCountedInStock();
        $leavingStock = !$this->isCountedInStock() && $from->isCountedInStock();
        return $enteringStock || $leavingStock;
    }
}
