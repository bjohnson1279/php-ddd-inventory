<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface;
use InventoryApp\Domain\Returns\Aggregates\QuarantineItem;
use InventoryApp\Domain\Returns\Enums\QuarantineStatus;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;

class InMemoryQuarantineRepository implements QuarantineRepositoryInterface
{
    private string $path;

    public function __construct(string $storagePath = null)
    {
        $root = $storagePath ?? __DIR__ . '\\\\..\\\\..\\\\..\\\\..\\\\storage\\\\data';
        if (!is_dir($root)) mkdir($root, 0777, true);
        $this->path = $root . DIRECTORY_SEPARATOR . 'quarantine_items.json';
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

    public function save(QuarantineItem $item): void
    {
        $rows = $this->read();
        $found = false;

        $row = [
            'id' => $item->getId(),
            'variant_id' => $item->getVariantId(),
            'quantity' => $item->getQuantity(),
            'reason' => $item->getReason(),
            'location_id' => $item->getLocationId()->getValue(),
            'tenant_id' => $item->getTenantId()->getValue(),
            'status' => $item->getStatus()->value,
            'created_at' => $item->getCreatedAt()->format(DATE_ATOM),
            'resolved_at' => $item->getResolvedAt() ? $item->getResolvedAt()->format(DATE_ATOM) : null
        ];

        foreach ($rows as &$r) {
            if ($r['id'] === $item->getId()) {
                $r = $row;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $rows[] = $row;
        }

        $this->write($rows);
    }

    public function findById(string $id): ?QuarantineItem
    {
        $rows = $this->read();
        foreach ($rows as $r) {
            if ($r['id'] === $id) {
                return $this->hydrate($r);
            }
        }
        return null;
    }

    public function findAllByTenant(string $tenantId): array
    {
        $rows = $this->read();
        $out = [];
        foreach ($rows as $r) {
            if ($r['tenant_id'] === $tenantId) {
                $out[] = $this->hydrate($r);
            }
        }
        return $out;
    }

    private function hydrate(array $r): QuarantineItem
    {
        return new QuarantineItem(
            $r['id'],
            $r['variant_id'],
            $r['quantity'],
            $r['reason'],
            new LocationId($r['location_id']),
            new TenantId($r['tenant_id']),
            QuarantineStatus::from($r['status']),
            new DateTimeImmutable($r['created_at']),
            $r['resolved_at'] ? new DateTimeImmutable($r['resolved_at']) : null
        );
    }
}
