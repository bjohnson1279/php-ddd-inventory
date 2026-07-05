<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;

class InMemoryLedgerRepository implements LedgerRepositoryInterface
{
    private string $path;

    public function __construct(string $storagePath = null)
    {
        $root = $storagePath ?? __DIR__ . '\\\\..\\\\..\\\\..\\\\..\\\\storage\\\\data';
        if (!is_dir($root)) mkdir($root, 0777, true);
        $this->path = $root . DIRECTORY_SEPARATOR . 'ledger_entries.json';
        if (!file_exists($this->path)) file_put_contents($this->path, json_encode([]));
    }

    private function read(): array
    {
        $data = json_decode(file_get_contents($this->path), true);
        return is_array($data) ? $data : [];
    }

    private function write(array $data): void
    {
        file_put_contents($this->path, json_encode(array_values($data), JSON_PRETTY_PRINT), LOCK_EX);
    }

    public function append(LedgerEntry $entry): void
    {
        $rows = $this->read();
        $rows[] = [
            'id' => $entry->id,
            'variantId' => $entry->variantId,
            'quantity' => $entry->quantity,
            'reason' => $entry->reason->value,
            'actorId' => $entry->actorId,
            'referenceId' => $entry->referenceId,
            'occurredAt' => $entry->occurredAt->format(DATE_ATOM),
            'metadata' => $entry->metadata,
        ];
        $this->write($rows);
    }

    public function currentQuantity(string $variantId): int
    {
        $rows = $this->read();
        $sum = 0;
        foreach ($rows as $r) {
            if ($r['variantId'] === $variantId) $sum += (int)$r['quantity'];
        }
        return $sum;
    }

    public function entriesFor(string $variantId, ?string $locationId = null): array
    {
        $rows = $this->read();
        $out = [];
        foreach ($rows as $r) {
            if ($r['variantId'] !== $variantId) continue;

            if ($locationId !== null) {
                $meta = $r['metadata'] ?? [];
                if (!isset($meta['locationId']) || $meta['locationId'] !== $locationId) {
                    continue;
                }
            }

            $out[] = new LedgerEntry(
                $r['id'],
                $r['variantId'],
                (int)$r['quantity'],
                \InventoryApp\Domain\Inventory\Enums\ReasonCode::from($r['reason']),
                $r['actorId'],
                $r['referenceId'] ?? null,
                new \DateTimeImmutable($r['occurredAt']),
                $r['metadata'] ?? [],
            );
        }
        return $out;
    }

    public function hasAnyEntries(string $variantId, string $locationId): bool
    {
        $rows = $this->read();
        foreach ($rows as $r) {
            if ($r['variantId'] === $variantId) {
                $meta = $r['metadata'] ?? [];
                if (isset($meta['locationId']) && $meta['locationId'] === $locationId) return true;
            }
        }
        return false;
    }

    public function findRecallEntries(string $lotNumber): array
    {
        $rows = $this->read();
        $out = [];
        foreach ($rows as $r) {
            $meta = $r['metadata'] ?? [];
            if (isset($meta['lotNumber']) && $meta['lotNumber'] === $lotNumber) {
                $out[] = new LedgerEntry(
                    $r['id'],
                    $r['variantId'],
                    (int)$r['quantity'],
                    \InventoryApp\Domain\Inventory\Enums\ReasonCode::from($r['reason']),
                    $r['actorId'],
                    $r['referenceId'] ?? null,
                    new \DateTimeImmutable($r['occurredAt']),
                    $r['metadata'] ?? [],
                );
            }
        }
        usort($out, fn($a, $b) => $b->occurredAt <=> $a->occurredAt);
        return $out;
    }
}
