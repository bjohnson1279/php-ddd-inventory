<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Barcode\Aggregates\VariantBarcodeSet;
use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSource;
use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;

class InMemoryBarcodeRepository implements BarcodeRepositoryInterface
{
    private string $path;

    public function __construct(string $storagePath = null)
    {
        $root = $storagePath ?? __DIR__ . '\\\\..\\\\..\\\\..\\\\..\\\\storage\\\\data';
        if (!is_dir($root)) mkdir($root, 0777, true);
        $this->path = $root . DIRECTORY_SEPARATOR . 'barcodes.json';
        if (!file_exists($this->path)) file_put_contents($this->path, json_encode([]));
    }

    private function read(): array { $data = json_decode(file_get_contents($this->path), true); return is_array($data) ? $data : []; }
    private function write(array $data): void { file_put_contents($this->path, json_encode(array_values($data), JSON_PRETTY_PRINT), LOCK_EX); }

    public function registerAssignment(string $variantId, Barcode $barcode, BarcodeSource $source, bool $isPrimary = false): void
    {
        $rows = $this->read();
        // global uniqueness check
        foreach ($rows as $r) {
            if (strtoupper($r['barcode_value']) === strtoupper($barcode->value)) throw new \DomainException('Barcode value already registered');
        }

        $rows[] = [
            'id' => bin2hex(random_bytes(8)),
            'variant_id' => $variantId,
            'barcode_value' => $barcode->value,
            'symbology' => $barcode->symbology->value,
            'source' => $source->value,
            'is_primary' => (bool)$isPrimary,
            'assigned_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->write($rows);
    }

    public function findVariantByBarcodeValue(string $value): ?string
    {
        $rows = $this->read();
        $v = strtoupper(trim($value));
        foreach ($rows as $r) {
            if (strtoupper($r['barcode_value']) === $v) return $r['variant_id'];
        }
        return null;
    }

    public function findSetForVariant(string $variantId): VariantBarcodeSet
    {
        $rows = $this->read();
        $set = new VariantBarcodeSet($variantId);
        foreach ($rows as $r) {
            if ($r['variant_id'] !== $variantId) continue;
            $barcode = new Barcode(BarcodeSymbology::from($r['symbology']), $r['barcode_value']);
            $set->assign($barcode, BarcodeSource::from($r['source']), $r['is_primary']);
        }
        return $set;
    }

    public function saveSet(VariantBarcodeSet $set): void
    {
        // naive: remove existing assignments for variant, then append
        $rows = array_filter($this->read(), fn($r) => $r['variant_id'] !== $set->variantId);
        foreach ($set->all() as $a) {
            $rows[] = [
                'id' => $a->id,
                'variant_id' => $a->variantId,
                'barcode_value' => $a->barcode->value,
                'symbology' => $a->barcode->symbology->value,
                'source' => $a->source->value,
                'is_primary' => $a->isPrimary,
                'assigned_at' => $a->assignedAt->format(DATE_ATOM),
            ];
        }
        $this->write($rows);
    }
}
