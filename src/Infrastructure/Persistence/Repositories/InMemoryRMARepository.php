<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Entities\RMAItem;
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use InventoryApp\Domain\Returns\Enums\RMAItemStatus;
use InventoryApp\Domain\Returns\Enums\RMADisposition;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use DateTimeImmutable;

class InMemoryRMARepository implements RMARepositoryInterface
{
    private string $path;

    public function __construct(string $storagePath = null)
    {
        $root = $storagePath ?? __DIR__ . '\\\\..\\\\..\\\\..\\\\..\\\\storage\\\\data';
        if (!is_dir($root)) mkdir($root, 0777, true);
        $this->path = $root . DIRECTORY_SEPARATOR . 'rmas.json';
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

    public function save(RMA $rma): void
    {
        $rows = $this->read();
        $found = false;

        $serializedItems = array_map(function (RMAItem $item) {
            return [
                'id' => $item->getId(),
                'variant_id' => $item->getVariantId(),
                'quantity' => $item->getQuantity(),
                'received_quantity' => $item->getReceivedQuantity(),
                'unit_cost_cents' => $item->getUnitCostCents(),
                'status' => $item->getStatus()->value,
                'disposition' => $item->getDisposition() ? $item->getDisposition()->value : null
            ];
        }, $rma->getItems());

        $row = [
            'id' => $rma->getId(),
            'rma_number' => $rma->getRmaNumber(),
            'tenant_id' => $rma->getTenantId()->getValue(),
            'customer_id' => $rma->getCustomerId(),
            'location_id' => $rma->getLocationId()->getValue(),
            'status' => $rma->getStatus()->value,
            'items' => $serializedItems,
            'created_at' => $rma->getCreatedAt()->format(DATE_ATOM),
            'updated_at' => $rma->getUpdatedAt()->format(DATE_ATOM)
        ];

        foreach ($rows as &$r) {
            if ($r['id'] === $rma->getId()) {
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

    public function findById(string $id): ?RMA
    {
        $rows = $this->read();
        foreach ($rows as $r) {
            if ($r['id'] === $id) {
                return $this->hydrate($r);
            }
        }
        return null;
    }

    public function findByNumber(string $rmaNumber): ?RMA
    {
        $rows = $this->read();
        foreach ($rows as $r) {
            if ($r['rma_number'] === $rmaNumber) {
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

    private function hydrate(array $r): RMA
    {
        $items = array_map(function ($item) {
            return new RMAItem(
                $item['id'],
                $item['variant_id'],
                $item['quantity'],
                $item['unit_cost_cents'],
                $item['received_quantity'],
                RMAItemStatus::from($item['status']),
                $item['disposition'] ? RMADisposition::from($item['disposition']) : null
            );
        }, $r['items']);

        return new RMA(
            $r['id'],
            $r['rma_number'],
            new TenantId($r['tenant_id']),
            $r['customer_id'],
            new LocationId($r['location_id']),
            RMAStatus::from($r['status']),
            $items,
            new DateTimeImmutable($r['created_at']),
            new DateTimeImmutable($r['updated_at'])
        );
    }
}
