<?php

namespace InventoryApp\Domain\Accounting\Repositories;

use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;

interface JournalRepositoryInterface
{
    public function save(JournalEntry $entry): void;

    /** Return raw saved rows for inspection/testing */
    public function all(): array;
}
