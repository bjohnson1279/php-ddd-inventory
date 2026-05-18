<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;

class InMemoryJournalRepository implements JournalRepositoryInterface
{
    private string $path;

    public function __construct(string $storagePath = null)
    {
        $root = $storagePath ?? __DIR__ . '\\\\..\\\\..\\\\..\\\\..\\\\storage\\\\data';
        if (!is_dir($root)) mkdir($root, 0777, true);
        $this->path = $root . DIRECTORY_SEPARATOR . 'journal_entries.json';
        if (!file_exists($this->path)) file_put_contents($this->path, json_encode([]));
    }

    private function read(): array { $data = json_decode(file_get_contents($this->path), true); return is_array($data) ? $data : []; }
    private function write(array $data): void { file_put_contents($this->path, json_encode(array_values($data), JSON_PRETTY_PRINT), LOCK_EX); }

    public function save(JournalEntry $entry): void
    {
        $rows = $this->read();
        $rows[] = [
            'id' => $entry->id,
            'tenantId' => $entry->tenantId,
            'date' => $entry->date->format('Y-m-d'),
            'description' => $entry->description,
            'referenceId' => $entry->referenceId,
            'method' => $entry->method->value,
            'lines' => array_map(fn($l) => ['account' => $l->account->code, 'amount' => $l->amountCents, 'type' => $l->type->value, 'memo' => $l->memo], $entry->lines()),
        ];
        $this->write($rows);
    }

    public function all(): array { return $this->read(); }
}
