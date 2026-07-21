<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;

class InMemorySerializedItemRepository implements SerializedItemRepositoryInterface
{
    private string $path;

    public function __construct(string $storagePath = null)
    {
        $root = $storagePath ?? __DIR__ . '\\\\..\\\\..\\\\..\\\\..\\\\storage\\\\data';
        if (!is_dir($root)) mkdir($root, 0777, true);
        $this->path = $root . DIRECTORY_SEPARATOR . 'serialized_items.json';
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

    public function isRegistered(SerialNumber $serial, string $tenantId): bool
    {
        $rows = $this->read();
        foreach ($rows as $r) {
            if (strtoupper($r['serialNumber']) === strtoupper($serial->value) && $r['tenantId'] === $tenantId) return true;
        }
        return false;
    }

    public function save(SerializedItem $item): void
    {
        $rows = $this->read();
        // serialize minimal fields
        $found = false;
        foreach ($rows as &$r) {
            if ($r['id'] === $item->id) {
                $r = $this->serializeItem($item);
                $found = true;
                break;
            }
        }
        if (!$found) $rows[] = $this->serializeItem($item);
        $this->write($rows);
    }

    private function serializeItem(SerializedItem $item): array
    {
        $history = array_map(function ($t) {
            return [
                'from' => $t->from->value,
                'to' => $t->to->value,
                'reason' => $t->reason,
                'actorId' => $t->actorId,
                'referenceId' => $t->referenceId,
                'occurredAt' => $t->occurredAt->format(DATE_ATOM),
            ];
        }, $item->history());

        return [
            'id' => $item->id,
            'variantId' => $item->variantId,
            'serialNumber' => $item->serialNumber->value,
            'tenantId' => $item->tenantId,
            'locationId' => $item->locationId(),
            'status' => $item->status()->value,
            'history' => $history,
        ];
    }

    public function findBySerialOrFail(SerialNumber $serial, string $tenantId): SerializedItem
    {
        $r = $this->findBySerial($serial, $tenantId);
        if ($r === null) throw new \DomainException('Serial not found');
        return $r;
    }

    public function findBySerial(SerialNumber $serial, string $tenantId): ?SerializedItem
    {
        $rows = $this->read();
        foreach ($rows as $r) {
            if (strtoupper($r['serialNumber']) === strtoupper($serial->value) && $r['tenantId'] === $tenantId) {
                return $this->hydrate($r);
            }
        }
        return null;
    }

    public function findById(string $id): ?SerializedItem
    {
        $rows = $this->read();
        foreach ($rows as $r) {
            if ($r['id'] === $id) return $this->hydrate($r);
        }
        return null;
    }

    public function findByVariant(string $variantId, ?SerializedItemStatus $status = null): array
    {
        $rows = $this->read();
        $out = [];
        foreach ($rows as $r) {
            if ($r['variantId'] !== $variantId) continue;
            if ($status !== null && $r['status'] !== $status->value) continue;
            $out[] = $this->hydrate($r);
        }
        return $out;
    }

    public function countByStatus(string $variantId, SerializedItemStatus $status): int
    {
        $rows = $this->read();
        $c = 0;
        foreach ($rows as $r) {
            if ($r['variantId'] === $variantId && $r['status'] === $status->value) $c++;
        }
        return $c;
    }

    private function hydrate(array $r): SerializedItem
    {
        $serial = new SerialNumber($r['serialNumber']);
        $item = new SerializedItem($r['id'], $r['variantId'], $serial, $r['tenantId'], $r['locationId'], SerializedItemStatus::from($r['status']));
        // history is not reconstructed into objects to keep simplicity
        return $item;
    }
}
