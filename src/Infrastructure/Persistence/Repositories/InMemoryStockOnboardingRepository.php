<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;
use InventoryApp\Domain\Inventory\Repositories\StockOnboardingRepositoryInterface;

class InMemoryStockOnboardingRepository implements StockOnboardingRepositoryInterface
{
    private string $path;

    public function __construct(string $storagePath = null)
    {
        $root = $storagePath ?? __DIR__ . '\\\\..\\\\..\\\\..\\\\..\\\\storage\\\\data';
        if (!is_dir($root)) mkdir($root, 0777, true);
        $this->path = $root . DIRECTORY_SEPARATOR . 'stock_onboardings.json';
        if (!file_exists($this->path)) file_put_contents($this->path, json_encode([]));
    }

    private function read(): array { $data = json_decode(file_get_contents($this->path), true); return is_array($data) ? $data : []; }
    private function write(array $data): void { file_put_contents($this->path, json_encode(array_values($data), JSON_PRETTY_PRINT), LOCK_EX); }

    public function save(StockOnboarding $onboarding): void
    {
        $rows = $this->read();
        // naive upsert
        $found = false;
        foreach ($rows as &$r) {
            if ($r['id'] === $onboarding->id) { $r = $this->serialize($onboarding); $found = true; break; }
        }
        if (!$found) $rows[] = $this->serialize($onboarding);
        $this->write($rows);
    }

    public function findOrFail(string $id): StockOnboarding
    {
        $rows = $this->read();
        foreach ($rows as $r) if ($r['id'] === $id) return $this->hydrate($r);
        throw new \DomainException('Onboarding not found');
    }

    private function serialize(StockOnboarding $o): array
    {
        $items = array_map(fn($it) => ['variantId' => $it->variantId, 'quantity' => $it->quantity, 'unitCostCents' => $it->unitCostCents], $o->items());
        return ['id' => $o->id, 'tenantId' => $o->tenantId, 'locationId' => $o->locationId, 'asOfDate' => $o->asOfDate->format(DATE_ATOM), 'status' => $o->isSubmitted() ? 'submitted' : 'draft', 'items' => $items];
    }

    private function hydrate(array $r): StockOnboarding
    {
        $o = new StockOnboarding($r['id'], $r['tenantId'], $r['locationId'], new \DateTimeImmutable($r['asOfDate']));
        foreach ($r['items'] as $it) {
            $o->setItem($it['variantId'], $it['quantity'], $it['unitCostCents']);
        }
        if (($r['status'] ?? '') === 'submitted') $o->submit();
        return $o;
    }
}
